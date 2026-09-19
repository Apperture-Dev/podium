package killswitch

import (
	"context"
	"errors"
	"fmt"

	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
	"k8s.io/client-go/kubernetes"

	"github.com/Apperture-Dev/podium/services/activator/internal/routing"
)

// Reconciler patches each entry's Ingress backend to route either through
// the activator itself (enabled) or directly to the tenant's own Service
// (disabled).
type Reconciler struct {
	client           kubernetes.Interface
	activatorService string
	activatorPort    int32
}

func NewReconciler(client kubernetes.Interface, activatorService string, activatorPort int32) *Reconciler {
	return &Reconciler{client: client, activatorService: activatorService, activatorPort: activatorPort}
}

// Apply patches every entry's Ingress. It keeps going past a single
// tenant's failure — a kill switch that gives up halfway through, leaving
// some tenants still routed through a misbehaving activator, defeats its
// own purpose — and returns every error it hit, joined.
func (r *Reconciler) Apply(ctx context.Context, entries []routing.Entry, enabled bool) error {
	var errs []error
	for _, e := range entries {
		if err := r.applyOne(ctx, e, enabled); err != nil {
			errs = append(errs, fmt.Errorf("%s/%s: %w", e.Namespace, e.IngressName, err))
		}
	}
	return errors.Join(errs...)
}

func (r *Reconciler) applyOne(ctx context.Context, e routing.Entry, enabled bool) error {
	backendName, backendPort := e.ServiceName, e.ServicePort
	if enabled {
		backendName, backendPort = r.activatorService, r.activatorPort
	}

	ingresses := r.client.NetworkingV1().Ingresses(e.Namespace)
	ing, err := ingresses.Get(ctx, e.IngressName, metav1.GetOptions{})
	if err != nil {
		return fmt.Errorf("get ingress: %w", err)
	}

	ing = ing.DeepCopy()
	for i := range ing.Spec.Rules {
		http := ing.Spec.Rules[i].HTTP
		if http == nil {
			continue
		}
		for j := range http.Paths {
			backend := http.Paths[j].Backend.Service
			if backend == nil {
				continue
			}
			backend.Name = backendName
			backend.Port.Number = backendPort
		}
	}

	if _, err := ingresses.Update(ctx, ing, metav1.UpdateOptions{}); err != nil {
		return fmt.Errorf("update ingress: %w", err)
	}
	return nil
}
