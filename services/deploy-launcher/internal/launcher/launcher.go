// Package launcher orchestrates one DeployAttemptRequested: get-or-create
// the tenant's ArgoCD Application, patch only its valuesObject when it
// already exists, wait for it to report health, and produce exactly one of
// HealthCheckSucceeded/HealthCheckExhausted — no independent logic beyond
// that wiring (the mapping lives in argospec, this package only sequences
// the calls). Mirrors services/build-launcher/internal/launcher.
package launcher

import (
	"context"
	"fmt"
	"time"

	apierrors "k8s.io/apimachinery/pkg/api/errors"
	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
	"k8s.io/apimachinery/pkg/apis/meta/v1/unstructured"
	"k8s.io/apimachinery/pkg/runtime/schema"
	"k8s.io/client-go/dynamic"

	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/argospec"
	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/events"
)

// ApplicationGVR identifies ArgoCD's Application CRD — argoproj.io isn't a
// built-in client-go type, so this launcher talks to it via the generic
// dynamic client instead of a typed clientset.
var ApplicationGVR = schema.GroupVersionResource{Group: "argoproj.io", Version: "v1alpha1", Resource: "applications"}

// Launcher creates/updates tenant Applications and watches their health.
type Launcher struct {
	client       dynamic.Interface
	cfg          argospec.Config
	pollInterval time.Duration
	maxAttempts  int
}

func New(client dynamic.Interface, cfg argospec.Config, pollInterval time.Duration, maxAttempts int) *Launcher {
	return &Launcher{client: client, cfg: cfg, pollInterval: pollInterval, maxAttempts: maxAttempts}
}

// Handle gets-or-creates the Application for req, waits for it to become
// healthy, and returns exactly one of (succeeded, failed) — never both,
// and never neither, unless ctx is cancelled or the Kubernetes API itself
// errors (err != nil).
func (l *Launcher) Handle(ctx context.Context, req events.DeployAttemptRequested) (succeeded *events.HealthCheckSucceeded, failed *events.HealthCheckExhausted, err error) {
	name := argospec.ApplicationName(req)
	apps := l.client.Resource(ApplicationGVR).Namespace(l.cfg.ArgoCDNamespace)

	existing, err := apps.Get(ctx, name, metav1.GetOptions{})
	switch {
	case apierrors.IsNotFound(err):
		app := argospec.Build(req, l.cfg)
		if _, err := apps.Create(ctx, app, metav1.CreateOptions{}); err != nil {
			return nil, nil, fmt.Errorf("create application %s: %w", name, err)
		}
	case err != nil:
		return nil, nil, fmt.Errorf("get application %s: %w", name, err)
	default:
		// Only the values object is touched — never replace the whole
		// object, so anything else ArgoCD or a human set on it survives.
		values := argospec.ValuesObject(req, l.cfg)
		if err := unstructured.SetNestedMap(existing.Object, values, "spec", "source", "helm", "valuesObject"); err != nil {
			return nil, nil, fmt.Errorf("set valuesObject on %s: %w", name, err)
		}
		if _, err := apps.Update(ctx, existing, metav1.UpdateOptions{}); err != nil {
			return nil, nil, fmt.Errorf("update application %s: %w", name, err)
		}
	}

	healthy, attempts, err := l.waitForHealthy(ctx, name)
	if err != nil {
		return nil, nil, err
	}
	if healthy {
		return &events.HealthCheckSucceeded{DeployAttemptID: req.DeployAttemptID}, nil, nil
	}

	return nil, &events.HealthCheckExhausted{
		DeployAttemptID: req.DeployAttemptID,
		ErrorMessage:    fmt.Sprintf("application %s did not report health \"Healthy\" after %d attempts", name, attempts),
		RetryCount:      &attempts,
	}, nil
}

func (l *Launcher) waitForHealthy(ctx context.Context, name string) (healthy bool, attempts int, err error) {
	apps := l.client.Resource(ApplicationGVR).Namespace(l.cfg.ArgoCDNamespace)
	ticker := time.NewTicker(l.pollInterval)
	defer ticker.Stop()

	for attempts = 1; attempts <= l.maxAttempts; attempts++ {
		app, err := apps.Get(ctx, name, metav1.GetOptions{})
		if apierrors.IsNotFound(err) {
			// La Application desapareció a mitad de sondeo (borrado manual,
			// p.ej. al recrear un tenant con una versión de chart nueva) —
			// señal terminal legítima, no un error de infraestructura:
			// nunca va a volver a existir sola, así que agota como
			// cualquier otro "nunca se puso sana" en vez de dejar el
			// intento colgado sin ack para siempre (visto en vivo).
			return false, attempts, nil
		}
		if err != nil {
			return false, attempts, fmt.Errorf("get application %s: %w", name, err)
		}

		status, _, _ := unstructured.NestedString(app.Object, "status", "health", "status")
		if status == "Healthy" {
			return true, attempts, nil
		}

		if attempts == l.maxAttempts {
			break
		}

		select {
		case <-ctx.Done():
			return false, attempts, ctx.Err()
		case <-ticker.C:
		}
	}

	return false, attempts, nil
}
