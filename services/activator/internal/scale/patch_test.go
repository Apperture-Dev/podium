package scale_test

import (
	"context"
	"testing"

	appsv1 "k8s.io/api/apps/v1"
	autoscalingv1 "k8s.io/api/autoscaling/v1"
	apierrors "k8s.io/apimachinery/pkg/api/errors"
	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
	"k8s.io/apimachinery/pkg/runtime"
	"k8s.io/apimachinery/pkg/runtime/schema"
	k8stesting "k8s.io/client-go/testing"

	fakeclient "k8s.io/client-go/kubernetes/fake"

	"github.com/Apperture-Dev/podium/services/activator/internal/scale"
)

func deployment(namespace, name string, replicas int32) *appsv1.Deployment {
	return &appsv1.Deployment{
		ObjectMeta: metav1.ObjectMeta{Name: name, Namespace: namespace},
		Spec:       appsv1.DeploymentSpec{Replicas: &replicas},
	}
}

// installFakeScale teaches the fake clientset how to serve the
// "deployments/scale" subresource by translating to/from the underlying
// Deployment object — the generated fake client doesn't implement this on
// its own (a known client-go gap, not something the production code can
// work around). Must be installed before any test-specific reactor that
// needs to intercept the same "get"/"update" actions, since PrependReactor
// makes the most-recently-added reactor run first.
func installFakeScale(client *fakeclient.Clientset) {
	tracker := client.Tracker()

	client.PrependReactor("get", "deployments", func(action k8stesting.Action) (bool, runtime.Object, error) {
		getAction, ok := action.(k8stesting.GetAction)
		if !ok || getAction.GetSubresource() != "scale" {
			return false, nil, nil
		}
		obj, err := tracker.Get(action.GetResource(), action.GetNamespace(), getAction.GetName())
		if err != nil {
			return true, nil, err
		}
		dep := obj.(*appsv1.Deployment)
		var replicas int32
		if dep.Spec.Replicas != nil {
			replicas = *dep.Spec.Replicas
		}
		return true, &autoscalingv1.Scale{
			ObjectMeta: dep.ObjectMeta,
			Spec:       autoscalingv1.ScaleSpec{Replicas: replicas},
			Status:     autoscalingv1.ScaleStatus{Replicas: replicas},
		}, nil
	})

	client.PrependReactor("update", "deployments", func(action k8stesting.Action) (bool, runtime.Object, error) {
		updateAction, ok := action.(k8stesting.UpdateAction)
		if !ok || updateAction.GetSubresource() != "scale" {
			return false, nil, nil
		}
		scaleObj := updateAction.GetObject().(*autoscalingv1.Scale)
		obj, err := tracker.Get(action.GetResource(), action.GetNamespace(), scaleObj.Name)
		if err != nil {
			return true, nil, err
		}
		dep := obj.(*appsv1.Deployment).DeepCopy()
		dep.Spec.Replicas = &scaleObj.Spec.Replicas
		if err := tracker.Update(action.GetResource(), dep, action.GetNamespace()); err != nil {
			return true, nil, err
		}
		return true, scaleObj, nil
	})
}

func TestSetReplicasUpdatesDeploymentScale(t *testing.T) {
	client := fakeclient.NewSimpleClientset(deployment("team-a", "app", 0))
	installFakeScale(client)
	p := scale.NewPatcher(client)

	if err := p.SetReplicas(context.Background(), "team-a", "app", 1); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	got, err := client.AppsV1().Deployments("team-a").Get(context.Background(), "app", metav1.GetOptions{})
	if err != nil {
		t.Fatalf("unexpected error reading back deployment: %v", err)
	}
	if got.Spec.Replicas == nil || *got.Spec.Replicas != 1 {
		t.Fatalf("expected replicas=1, got %+v", got.Spec.Replicas)
	}
}

func TestSetReplicasIsANoopWhenAlreadyAtTarget(t *testing.T) {
	client := fakeclient.NewSimpleClientset(deployment("team-a", "app", 1))
	installFakeScale(client)
	updateCalls := 0
	client.PrependReactor("update", "deployments", func(action k8stesting.Action) (bool, runtime.Object, error) {
		if action.GetSubresource() == "scale" {
			updateCalls++
		}
		return false, nil, nil
	})
	p := scale.NewPatcher(client)

	if err := p.SetReplicas(context.Background(), "team-a", "app", 1); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if updateCalls != 0 {
		t.Fatalf("expected no UpdateScale call when already at target replicas, got %d", updateCalls)
	}
}

func TestSetReplicasRetriesOnConflict(t *testing.T) {
	client := fakeclient.NewSimpleClientset(deployment("team-a", "app", 0))
	installFakeScale(client)
	attempts := 0
	client.PrependReactor("update", "deployments", func(action k8stesting.Action) (bool, runtime.Object, error) {
		if action.GetSubresource() != "scale" {
			return false, nil, nil
		}
		attempts++
		if attempts < 3 {
			return true, nil, apierrConflict()
		}
		return false, nil, nil
	})
	p := scale.NewPatcher(client)

	if err := p.SetReplicas(context.Background(), "team-a", "app", 1); err != nil {
		t.Fatalf("expected the patch to eventually succeed after retrying conflicts, got: %v", err)
	}
	if attempts != 3 {
		t.Fatalf("expected exactly 3 attempts (2 conflicts + 1 success), got %d", attempts)
	}
}

func TestSetReplicasReturnsDeploymentNotFound(t *testing.T) {
	client := fakeclient.NewSimpleClientset()
	installFakeScale(client)
	p := scale.NewPatcher(client)

	err := p.SetReplicas(context.Background(), "team-a", "missing", 1)
	if err == nil {
		t.Fatal("expected an error for a Deployment that does not exist")
	}
}

func apierrConflict() error {
	gr := schema.GroupResource{Group: "apps", Resource: "deployments"}
	return apierrors.NewConflict(gr, "app", nil)
}
