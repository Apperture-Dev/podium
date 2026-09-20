// Command activator wires together the routing table, the wake-on-request
// proxy, the idle sweep, and the kill switch. It's pure orchestration — no
// independent logic lives here, only informer/HTTP plumbing, so it isn't
// unit tested (see internal/* for the tested pieces this wires up).
package main

import (
	"context"
	"log/slog"
	"net"
	"net/http"
	"net/http/httputil"
	"net/url"
	"os"
	"os/signal"
	"strconv"
	"syscall"
	"time"

	corev1 "k8s.io/api/core/v1"
	networkingv1 "k8s.io/api/networking/v1"
	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
	"k8s.io/client-go/informers"
	"k8s.io/client-go/kubernetes"
	"k8s.io/client-go/kubernetes/scheme"
	typedcorev1 "k8s.io/client-go/kubernetes/typed/core/v1"
	"k8s.io/client-go/rest"
	"k8s.io/client-go/tools/cache"
	"k8s.io/client-go/tools/clientcmd"
	"k8s.io/client-go/tools/record"

	"github.com/Apperture-Dev/podium/services/activator/internal/killswitch"
	"github.com/Apperture-Dev/podium/services/activator/internal/lastseen"
	"github.com/Apperture-Dev/podium/services/activator/internal/proxy"
	"github.com/Apperture-Dev/podium/services/activator/internal/routing"
	"github.com/Apperture-Dev/podium/services/activator/internal/scale"
	"github.com/Apperture-Dev/podium/services/activator/internal/sweep"
	"github.com/Apperture-Dev/podium/services/activator/internal/wake"
)

const (
	managedLabel      = "podium.dev/managed"
	managedLabelValue = "true"
	stuckWakeBudget   = 60 * time.Second
	wakePollInterval  = 500 * time.Millisecond
)

func main() {
	logger := slog.New(slog.NewJSONHandler(os.Stdout, nil))

	namespace := envOrDefault("ACTIVATOR_NAMESPACE", "hostium")
	httpAddr := envOrDefault("ACTIVATOR_HTTP_ADDR", ":8080")
	activatorService := envOrDefault("ACTIVATOR_SERVICE_NAME", "activator")
	activatorPort := envIntOrDefault("ACTIVATOR_SERVICE_PORT", 8080)
	idleThreshold := envDurationOrDefault("ACTIVATOR_IDLE_THRESHOLD", 15*time.Minute)
	sweepInterval := envDurationOrDefault("ACTIVATOR_SWEEP_INTERVAL", 30*time.Second)

	client, err := k8sClient()
	if err != nil {
		logger.Error("building kubernetes client", "err", err)
		os.Exit(1)
	}

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	table := routing.NewTable()
	seen := lastseen.NewTracker()
	patcher := scale.NewPatcher(client)
	recorder := eventRecorder(client, logger)

	// Only Ingresses are watched to build the routing table: they carry the
	// Host this table is keyed by (via annotations set by the chart that
	// creates them — see upsertFromIngress). Deployment readiness/scale
	// checks go straight to the API (deploymentReady, scale.Patcher)
	// instead of through a second informer's lister — the routing table is
	// small (one entry per active tenant), so a live Get per request isn't
	// worth trading for the extra cache-consistency surface.
	informerFactory := informers.NewSharedInformerFactoryWithOptions(client, 30*time.Second,
		informers.WithTweakListOptions(func(opts *metav1.ListOptions) {
			opts.LabelSelector = managedLabel + "=" + managedLabelValue
		}),
	)
	ingressInformer := informerFactory.Networking().V1().Ingresses().Informer()
	installRouteHandlers(table, ingressInformer, logger)

	informerFactory.Start(ctx.Done())
	informerFactory.WaitForCacheSync(ctx.Done())

	waker := wake.New(func(ctx context.Context, host string) error {
		entry, ok := table.Lookup(host)
		if !ok {
			return nil
		}
		start := time.Now()
		if err := patcher.SetReplicas(ctx, entry.Namespace, entry.DeploymentName, 1); err != nil {
			return err
		}
		return waitReady(ctx, client, entry, start, recorder, logger)
	})

	handler := &proxy.Handler{
		Table: table,
		Seen:  seen,
		Waker: waker,
		Ready: func(ctx context.Context, e routing.Entry) (bool, error) {
			return deploymentReady(ctx, client, e.Namespace, e.DeploymentName)
		},
		Backend: func(e routing.Entry) http.Handler {
			return httputil.NewSingleHostReverseProxy(&url.URL{
				Scheme: "http",
				Host:   net.JoinHostPort(e.ServiceName+"."+e.Namespace+".svc.cluster.local", strconv.Itoa(int(e.ServicePort))),
			})
		},
	}

	flag := killswitch.NewFlag()
	reconciler := killswitch.NewReconciler(client, activatorService, int32(activatorPort))
	installKillSwitchWatch(ctx, client, namespace, flag, table, reconciler, logger)

	sweeper := &sweep.Sweeper{Table: table, Seen: seen, Patcher: patcher, Threshold: idleThreshold}
	go runSweepLoop(ctx, sweeper, sweepInterval, logger)

	server := &http.Server{Addr: httpAddr, Handler: handler}
	go func() {
		<-ctx.Done()
		shutdownCtx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
		defer cancel()
		_ = server.Shutdown(shutdownCtx)
	}()

	logger.Info("activator listening", "addr", httpAddr)
	if err := server.ListenAndServe(); err != nil && err != http.ErrServerClosed {
		logger.Error("http server exited", "err", err)
		os.Exit(1)
	}
}

func k8sClient() (kubernetes.Interface, error) {
	cfg, err := rest.InClusterConfig()
	if err != nil {
		kubeconfig := os.Getenv("KUBECONFIG")
		if kubeconfig == "" {
			kubeconfig = os.Getenv("HOME") + "/.kube/config"
		}
		cfg, err = clientcmd.BuildConfigFromFlags("", kubeconfig)
		if err != nil {
			return nil, err
		}
	}
	return kubernetes.NewForConfig(cfg)
}

func eventRecorder(client kubernetes.Interface, logger *slog.Logger) record.EventRecorder {
	broadcaster := record.NewBroadcaster()
	broadcaster.StartRecordingToSink(&typedcorev1.EventSinkImpl{Interface: client.CoreV1().Events("")})
	return broadcaster.NewRecorder(scheme.Scheme, corev1.EventSource{Component: "activator"})
}

// installRouteHandlers keeps table in sync with labeled Ingresses — see the
// comment where the informer is built for why Deployments/Services aren't
// watched too.
func installRouteHandlers(table *routing.Table, ingInf cache.SharedIndexInformer, logger *slog.Logger) {
	ingHandler := cache.ResourceEventHandlerFuncs{
		AddFunc:    func(obj any) { upsertFromIngress(table, obj, logger) },
		UpdateFunc: func(_, obj any) { upsertFromIngress(table, obj, logger) },
		DeleteFunc: func(obj any) { deleteFromIngress(table, obj, logger) },
	}
	if _, err := ingInf.AddEventHandler(ingHandler); err != nil {
		logger.Error("watching ingresses", "err", err)
	}
}

func upsertFromIngress(table *routing.Table, obj any, logger *slog.Logger) {
	ing, ok := obj.(*networkingv1.Ingress)
	if !ok || len(ing.Spec.Rules) == 0 {
		return
	}
	ann := ing.Annotations
	deployment := ann["podium.dev/deployment-name"]
	if deployment == "" {
		deployment = ing.Labels["app"]
	}
	for _, rule := range ing.Spec.Rules {
		if rule.Host == "" || rule.HTTP == nil || len(rule.HTTP.Paths) == 0 {
			continue
		}
		backend := rule.HTTP.Paths[0].Backend.Service
		if backend == nil {
			continue
		}
		table.Set(routing.Entry{
			Host:           rule.Host,
			Namespace:      ing.Namespace,
			DeploymentName: deployment,
			ServiceName:    backend.Name,
			ServicePort:    backend.Port.Number,
			IngressName:    ing.Name,
		})
	}
}

func deleteFromIngress(table *routing.Table, obj any, logger *slog.Logger) {
	ing, ok := obj.(*networkingv1.Ingress)
	if !ok {
		return
	}
	for _, rule := range ing.Spec.Rules {
		if rule.Host != "" {
			table.Delete(rule.Host)
		}
	}
}

func deploymentReady(ctx context.Context, client kubernetes.Interface, namespace, name string) (bool, error) {
	dep, err := client.AppsV1().Deployments(namespace).Get(ctx, name, metav1.GetOptions{})
	if err != nil {
		return false, err
	}
	return dep.Status.ReadyReplicas > 0, nil
}

// waitReady polls until entry's Deployment is ready or the context is
// done, emitting a Warning Event if it's still not ready after
// stuckWakeBudget — a normal cold start shouldn't take that long; one that
// does is a new diagnostic surface for the remediation agent (uncached
// image, pull failure, crashloop, exhausted quota), not routine.
func waitReady(ctx context.Context, client kubernetes.Interface, entry routing.Entry, start time.Time, recorder record.EventRecorder, logger *slog.Logger) error {
	alerted := false
	ticker := time.NewTicker(wakePollInterval)
	defer ticker.Stop()
	for {
		ready, err := deploymentReady(ctx, client, entry.Namespace, entry.DeploymentName)
		if err != nil {
			return err
		}
		if ready {
			return nil
		}
		if !alerted && time.Since(start) > stuckWakeBudget {
			alerted = true
			if dep, err := client.AppsV1().Deployments(entry.Namespace).Get(ctx, entry.DeploymentName, metav1.GetOptions{}); err == nil {
				recorder.Eventf(dep, corev1.EventTypeWarning, "ActivatorWakeTimeout",
					"wake for %s/%s exceeded the %s cold-start budget — check for an uncached image, a pull failure, a crashloop, or exhausted quota",
					entry.Namespace, entry.DeploymentName, stuckWakeBudget)
			}
			logger.Warn("wake exceeded budget", "namespace", entry.Namespace, "deployment", entry.DeploymentName)
		}
		select {
		case <-ctx.Done():
			return ctx.Err()
		case <-ticker.C:
		}
	}
}

// installKillSwitchWatch watches the "activator-config" ConfigMap in
// namespace for its "enabled" key and reconciles every known tenant's
// Ingress whenever it flips — see internal/killswitch for why cutover and
// kill-switch are the same operation in opposite directions.
func installKillSwitchWatch(ctx context.Context, client kubernetes.Interface, namespace string, flag *killswitch.Flag, table *routing.Table, reconciler *killswitch.Reconciler, logger *slog.Logger) {
	factory := informers.NewSharedInformerFactoryWithOptions(client, 30*time.Second, informers.WithNamespace(namespace))
	cmInformer := factory.Core().V1().ConfigMaps().Informer()

	apply := func(obj any) {
		cm, ok := obj.(*corev1.ConfigMap)
		if !ok || cm.Name != "activator-config" {
			return
		}
		enabled := cm.Data["enabled"] == "true"
		if enabled == flag.Enabled() {
			return
		}
		flag.Set(enabled)
		entries := entriesOf(table)
		if err := reconciler.Apply(ctx, entries, enabled); err != nil {
			logger.Error("applying kill switch state", "enabled", enabled, "err", err)
		} else {
			logger.Info("kill switch applied", "enabled", enabled, "tenants", len(entries))
		}
	}

	if _, err := cmInformer.AddEventHandler(cache.ResourceEventHandlerFuncs{
		AddFunc:    apply,
		UpdateFunc: func(_, obj any) { apply(obj) },
	}); err != nil {
		logger.Error("watching activator-config", "err", err)
	}
	factory.Start(ctx.Done())
	factory.WaitForCacheSync(ctx.Done())
}

func entriesOf(table *routing.Table) []routing.Entry {
	hosts := table.Hosts()
	entries := make([]routing.Entry, 0, len(hosts))
	for _, h := range hosts {
		if e, ok := table.Lookup(h); ok {
			entries = append(entries, e)
		}
	}
	return entries
}

func runSweepLoop(ctx context.Context, sweeper *sweep.Sweeper, interval time.Duration, logger *slog.Logger) {
	ticker := time.NewTicker(interval)
	defer ticker.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			if err := sweeper.Run(ctx); err != nil {
				logger.Error("sweep pass", "err", err)
			}
		}
	}
}

func envOrDefault(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}

func envIntOrDefault(key string, def int) int {
	if v := os.Getenv(key); v != "" {
		if n, err := strconv.Atoi(v); err == nil {
			return n
		}
	}
	return def
}

func envDurationOrDefault(key string, def time.Duration) time.Duration {
	if v := os.Getenv(key); v != "" {
		if d, err := time.ParseDuration(v); err == nil {
			return d
		}
	}
	return def
}
