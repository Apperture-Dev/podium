package sweep_test

import (
	"context"
	"testing"
	"time"

	appsv1 "k8s.io/api/apps/v1"
	autoscalingv1 "k8s.io/api/autoscaling/v1"
	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
	"k8s.io/apimachinery/pkg/runtime"
	fakeclient "k8s.io/client-go/kubernetes/fake"
	k8stesting "k8s.io/client-go/testing"

	"github.com/Apperture-Dev/podium/services/activator/internal/lastseen"
	"github.com/Apperture-Dev/podium/services/activator/internal/routing"
	"github.com/Apperture-Dev/podium/services/activator/internal/scale"
	"github.com/Apperture-Dev/podium/services/activator/internal/sweep"
)

// installFakeScale mirrors the identically-named helper in the scale
// package's own tests — the fake clientset needs the same translation
// shim for the "deployments/scale" subresource here too.
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
		return true, &autoscalingv1.Scale{ObjectMeta: dep.ObjectMeta, Spec: autoscalingv1.ScaleSpec{Replicas: replicas}}, nil
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

func deployment(namespace, name string, replicas int32) *appsv1.Deployment {
	return &appsv1.Deployment{
		ObjectMeta: metav1.ObjectMeta{Name: name, Namespace: namespace},
		Spec:       appsv1.DeploymentSpec{Replicas: &replicas},
	}
}

func replicasOf(t *testing.T, client *fakeclient.Clientset, namespace, name string) int32 {
	t.Helper()
	dep, err := client.AppsV1().Deployments(namespace).Get(context.Background(), name, metav1.GetOptions{})
	if err != nil {
		t.Fatalf("unexpected error reading back deployment: %v", err)
	}
	return *dep.Spec.Replicas
}

func TestRunScalesIdleHostsToZero(t *testing.T) {
	client := fakeclient.NewSimpleClientset(deployment("team-a", "app", 1))
	installFakeScale(client)

	table := routing.NewTable()
	table.Set(routing.Entry{Host: "team-a.apperture.dev", Namespace: "team-a", DeploymentName: "app"})

	now := time.Now()
	seen := lastseen.NewTrackerWithClock(func() time.Time { return now })
	seen.Touch("team-a.apperture.dev")
	now = now.Add(2 * time.Hour) // well past the threshold, never touched again

	s := &sweep.Sweeper{Table: table, Seen: seen, Patcher: scale.NewPatcher(client), Threshold: time.Hour}

	if err := s.Run(context.Background()); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if got := replicasOf(t, client, "team-a", "app"); got != 0 {
		t.Fatalf("expected idle host to be scaled to 0, got %d replicas", got)
	}
}

func TestRunSkipsHostsThatAreNotIdle(t *testing.T) {
	client := fakeclient.NewSimpleClientset(deployment("team-a", "app", 1))
	installFakeScale(client)

	table := routing.NewTable()
	table.Set(routing.Entry{Host: "team-a.apperture.dev", Namespace: "team-a", DeploymentName: "app"})

	seen := lastseen.NewTracker()
	seen.Touch("team-a.apperture.dev") // just now — not idle

	s := &sweep.Sweeper{Table: table, Seen: seen, Patcher: scale.NewPatcher(client), Threshold: time.Hour}

	if err := s.Run(context.Background()); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if got := replicasOf(t, client, "team-a", "app"); got != 1 {
		t.Fatalf("expected a recently-seen host to be left alone, got %d replicas", got)
	}
}

func TestRunContinuesPastOneFailureAndReportsIt(t *testing.T) {
	client := fakeclient.NewSimpleClientset(deployment("team-b", "app", 1))
	installFakeScale(client)

	table := routing.NewTable()
	table.Set(routing.Entry{Host: "team-a.apperture.dev", Namespace: "team-a", DeploymentName: "missing"})
	table.Set(routing.Entry{Host: "team-b.apperture.dev", Namespace: "team-b", DeploymentName: "app"})

	seen := lastseen.NewTracker() // both idle: never touched

	s := &sweep.Sweeper{Table: table, Seen: seen, Patcher: scale.NewPatcher(client), Threshold: time.Hour}

	err := s.Run(context.Background())
	if err == nil {
		t.Fatal("expected an error reporting team-a's missing deployment")
	}
	if got := replicasOf(t, client, "team-b", "app"); got != 0 {
		t.Fatalf("expected team-b to still be scaled down despite team-a failing, got %d replicas", got)
	}
}
