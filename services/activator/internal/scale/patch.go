// Package scale patches a Deployment's replica count through the "scale"
// subresource only — never the Deployment object as a whole, so a bug here
// cannot touch the image, env vars, or limits the poller or the agent might
// be writing to the same object concurrently.
package scale

import (
	"context"
	"fmt"

	autoscalingv1 "k8s.io/api/autoscaling/v1"
	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
	"k8s.io/client-go/kubernetes"
	"k8s.io/client-go/util/retry"
)

// Patcher sets a Deployment's replica count via the scale subresource.
type Patcher struct {
	client kubernetes.Interface
}

func NewPatcher(client kubernetes.Interface) *Patcher {
	return &Patcher{client: client}
}

// SetReplicas sets namespace/deployment's replica count to replicas. It is
// a no-op if the Deployment is already at that count, and retries on
// version conflicts (the same object may be written concurrently by the
// poller or the agent).
func (p *Patcher) SetReplicas(ctx context.Context, namespace, deployment string, replicas int32) error {
	return retry.RetryOnConflict(retry.DefaultRetry, func() error {
		current, err := p.client.AppsV1().Deployments(namespace).GetScale(ctx, deployment, metav1.GetOptions{})
		if err != nil {
			return fmt.Errorf("get scale for %s/%s: %w", namespace, deployment, err)
		}
		if current.Spec.Replicas == replicas {
			return nil
		}
		current.Spec.Replicas = replicas
		_, err = p.client.AppsV1().Deployments(namespace).UpdateScale(ctx, deployment, &autoscalingv1.Scale{
			ObjectMeta: current.ObjectMeta,
			Spec:       autoscalingv1.ScaleSpec{Replicas: replicas},
		}, metav1.UpdateOptions{})
		if err != nil {
			return fmt.Errorf("update scale for %s/%s: %w", namespace, deployment, err)
		}
		return nil
	})
}
