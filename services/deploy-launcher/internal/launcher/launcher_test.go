package launcher_test

import (
	"context"
	"testing"
	"time"

	apierrors "k8s.io/apimachinery/pkg/api/errors"
	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
	"k8s.io/apimachinery/pkg/apis/meta/v1/unstructured"
	"k8s.io/apimachinery/pkg/runtime"
	"k8s.io/apimachinery/pkg/runtime/schema"
	dynamicfake "k8s.io/client-go/dynamic/fake"
	k8stesting "k8s.io/client-go/testing"

	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/argospec"
	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/events"
	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/launcher"
)

func testConfig() argospec.Config {
	return argospec.Config{
		ArgoCDNamespace: "argocd",
		ChartRepoURL:    "registry.apperture.dev/charts",
		ChartName:       "podium-app",
		ChartVersion:    "0.1.0",
		BaseDomain:      "apperture.dev",
	}
}

func testRequest() events.DeployAttemptRequested {
	return events.DeployAttemptRequested{
		DeployAttemptID: "01HXYZ0123456789ABCDEFGHJK",
		Hash:            "abc12345",
		ServiceName:     "backend",
		Image:           "registry.apperture.dev/team-a-backend:v1",
	}
}

func newFakeClient() *dynamicfake.FakeDynamicClient {
	scheme := runtime.NewScheme()
	return dynamicfake.NewSimpleDynamicClientWithCustomListKinds(scheme, map[schema.GroupVersionResource]string{
		launcher.ApplicationGVR: "ApplicationList",
	})
}

// newSeededClient returns a client with an existing Application already in
// the tracker — Handle()'s existence-check Get and Update need a real
// object to find/patch, not just whatever a reactor fabricates in memory.
func newSeededClient(name string) *dynamicfake.FakeDynamicClient {
	scheme := runtime.NewScheme()
	seed := &unstructured.Unstructured{Object: map[string]any{
		"apiVersion": "argoproj.io/v1alpha1",
		"kind":       "Application",
		"metadata":   map[string]any{"name": name, "namespace": "argocd"},
		"spec": map[string]any{
			"source": map[string]any{
				"helm": map[string]any{"valuesObject": map[string]any{}},
			},
		},
		"status": map[string]any{"health": map[string]any{"status": "Progressing"}},
	}}
	return dynamicfake.NewSimpleDynamicClientWithCustomListKinds(scheme, map[schema.GroupVersionResource]string{
		launcher.ApplicationGVR: "ApplicationList",
	}, seed)
}

// withHealthSequence makes successive "get applications" calls (AFTER the
// one Handle() uses for its existence check, which falls through to the
// real tracker so Update has something to update against) return each
// health status in order, then keep returning the last one — simulates
// polling an Application that starts Progressing and eventually settles,
// without any real waiting.
func withHealthSequence(client *dynamicfake.FakeDynamicClient, name string, statuses []string) {
	call := 0
	client.PrependReactor("get", "applications", func(action k8stesting.Action) (bool, runtime.Object, error) {
		call++
		if call == 1 {
			return false, nil, nil
		}
		idx := call - 2
		if idx >= len(statuses) {
			idx = len(statuses) - 1
		}
		app := &unstructured.Unstructured{Object: map[string]any{
			"apiVersion": "argoproj.io/v1alpha1",
			"kind":       "Application",
			"metadata":   map[string]any{"name": name, "namespace": "argocd"},
			"status":     map[string]any{"health": map[string]any{"status": statuses[idx]}},
		}}
		return true, app, nil
	})
}

// Handle() calcula el nombre de la Application una vez, para el Get de
// existencia Y para el polling de salud — si esos dos usos divergen del
// nombre que argospec.Build() realmente usó para crear el objeto (bug real,
// visto en vivo: creaba "tenant-{serviceName}-{hash}" pero el polling
// buscaba "tenant-{hash}"), waitForHealthy sondea un nombre que nunca va a
// existir y Handle() nunca produce ni HealthCheckSucceeded ni
// HealthCheckExhausted — el intento se queda colgado para siempre.
func TestHandleGetsAndPollsTheSameNameArgospecCreated(t *testing.T) {
	client := newFakeClient()
	l := launcher.New(client, testConfig(), time.Millisecond, 5)

	wantName := "tenant-backend-abc12345"
	getCalls := 0
	client.PrependReactor("get", "applications", func(action k8stesting.Action) (bool, runtime.Object, error) {
		getCalls++
		getAction := action.(k8stesting.GetActionImpl)
		if getAction.GetName() != wantName {
			t.Errorf("Get #%d used name %q, want %q", getCalls, getAction.GetName(), wantName)
		}
		if getCalls == 1 {
			return true, nil, apierrors.NewNotFound(schema.GroupResource{Group: "argoproj.io", Resource: "applications"}, getAction.GetName())
		}
		app := &unstructured.Unstructured{Object: map[string]any{
			"status": map[string]any{"health": map[string]any{"status": "Healthy"}},
		}}
		return true, app, nil
	})

	succeeded, failed, err := l.Handle(context.Background(), testRequest())
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if failed != nil {
		t.Fatalf("expected no HealthCheckExhausted, got %+v", failed)
	}
	if succeeded == nil {
		t.Fatal("expected HealthCheckSucceeded, got nil")
	}
	if getCalls < 2 {
		t.Fatalf("expected at least 2 Get calls (existence check + poll), got %d", getCalls)
	}
}

func TestHandleCreatesTheApplicationWhenItDoesNotExist(t *testing.T) {
	client := newFakeClient()
	l := launcher.New(client, testConfig(), time.Millisecond, 5)

	// First Get (existence check inside Handle) returns NotFound; after
	// Create, polling Gets return Healthy immediately.
	getCalls := 0
	client.PrependReactor("get", "applications", func(action k8stesting.Action) (bool, runtime.Object, error) {
		getCalls++
		if getCalls == 1 {
			return true, nil, apierrors.NewNotFound(schema.GroupResource{Group: "argoproj.io", Resource: "applications"}, "tenant-backend-abc12345")
		}
		app := &unstructured.Unstructured{Object: map[string]any{
			"status": map[string]any{"health": map[string]any{"status": "Healthy"}},
		}}
		return true, app, nil
	})

	succeeded, failed, err := l.Handle(context.Background(), testRequest())
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if failed != nil {
		t.Fatalf("expected no HealthCheckExhausted, got %+v", failed)
	}
	if succeeded == nil || succeeded.DeployAttemptID != testRequest().DeployAttemptID {
		t.Fatalf("expected HealthCheckSucceeded for %s, got %+v", testRequest().DeployAttemptID, succeeded)
	}
}

func TestHandleUpdatesOnlyTheValuesObjectWhenTheApplicationAlreadyExists(t *testing.T) {
	scheme := runtime.NewScheme()
	existing := &unstructured.Unstructured{Object: map[string]any{
		"apiVersion": "argoproj.io/v1alpha1",
		"kind":       "Application",
		"metadata": map[string]any{
			"name":      "tenant-backend-abc12345",
			"namespace": "argocd",
			"labels":    map[string]any{"custom-label": "keep-me"},
		},
		"spec": map[string]any{
			"source": map[string]any{
				"repoURL": "registry.apperture.dev/charts",
				"chart":   "podium-app",
				"helm": map[string]any{
					"valuesObject": map[string]any{"hash": "old-hash"},
				},
			},
		},
		"status": map[string]any{"health": map[string]any{"status": "Healthy"}},
	}}
	client := dynamicfake.NewSimpleDynamicClientWithCustomListKinds(scheme, map[schema.GroupVersionResource]string{
		launcher.ApplicationGVR: "ApplicationList",
	}, existing)

	l := launcher.New(client, testConfig(), time.Millisecond, 5)

	succeeded, failed, err := l.Handle(context.Background(), testRequest())
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if failed != nil {
		t.Fatalf("expected no HealthCheckExhausted, got %+v", failed)
	}
	if succeeded == nil {
		t.Fatal("expected HealthCheckSucceeded")
	}

	got, err := client.Resource(launcher.ApplicationGVR).Namespace("argocd").Get(context.Background(), "tenant-backend-abc12345", metav1.GetOptions{})
	if err != nil {
		t.Fatalf("unexpected error reading back application: %v", err)
	}
	label, _, _ := unstructured.NestedString(got.Object, "metadata", "labels", "custom-label")
	if label != "keep-me" {
		t.Fatalf("expected the pre-existing label to survive the update, got %q", label)
	}
	hash, _, _ := unstructured.NestedString(got.Object, "spec", "source", "helm", "valuesObject", "hash")
	if hash != "abc12345" {
		t.Fatalf("expected valuesObject.hash to be updated to the new request's hash, got %q", hash)
	}
}

func TestHandleReturnsHealthCheckSucceededOncePollingSeesHealthy(t *testing.T) {
	client := newSeededClient("tenant-backend-abc12345")
	withHealthSequence(client, "tenant-backend-abc12345", []string{"Progressing", "Progressing", "Healthy"})
	l := launcher.New(client, testConfig(), time.Millisecond, 10)

	succeeded, failed, err := l.Handle(context.Background(), testRequest())
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if failed != nil {
		t.Fatalf("expected no HealthCheckExhausted, got %+v", failed)
	}
	if succeeded == nil {
		t.Fatal("expected HealthCheckSucceeded")
	}
}

func TestHandleReturnsHealthCheckExhaustedWhenNeverHealthy(t *testing.T) {
	client := newSeededClient("tenant-backend-abc12345")
	withHealthSequence(client, "tenant-backend-abc12345", []string{"Degraded"})
	l := launcher.New(client, testConfig(), time.Millisecond, 3)

	succeeded, failed, err := l.Handle(context.Background(), testRequest())
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if succeeded != nil {
		t.Fatalf("expected no HealthCheckSucceeded, got %+v", succeeded)
	}
	if failed == nil {
		t.Fatal("expected HealthCheckExhausted")
	}
	if failed.DeployAttemptID != testRequest().DeployAttemptID {
		t.Fatalf("got deployAttemptId %q", failed.DeployAttemptID)
	}
	if failed.RetryCount == nil || *failed.RetryCount != 3 {
		t.Fatalf("expected retryCount=3, got %v", failed.RetryCount)
	}
}

// Visto en vivo: borrar manualmente la Application (p.ej. al recrear un
// tenant con una versión de chart nueva) MIENTRAS deploy-launcher estaba a
// mitad de sondear su salud dejaba el intento colgado para siempre — Get
// devolvía NotFound, waitForHealthy lo propagaba como error, Handle()
// nunca producía ni HealthCheckSucceeded ni HealthCheckExhausted, y el
// mensaje se quedaba sin ack en el stream indefinidamente (confirmado con
// XPENDING contra el clúster real). Un NotFound a mitad de sondeo es una
// señal terminal legítima — la Application ya no existe, seguir
// reintentando no tiene sentido — así que debe agotar como cualquier otro
// caso de "nunca se puso sana", no como un error de infraestructura.
func TestHandleReturnsHealthCheckExhaustedWhenTheApplicationIsDeletedMidPoll(t *testing.T) {
	client := newSeededClient("tenant-backend-abc12345")
	call := 0
	client.PrependReactor("get", "applications", func(action k8stesting.Action) (bool, runtime.Object, error) {
		call++
		if call == 1 {
			return false, nil, nil // existence check dentro de Handle: deja pasar al Update
		}
		return true, nil, apierrors.NewNotFound(schema.GroupResource{Group: "argoproj.io", Resource: "applications"}, "tenant-backend-abc12345")
	})

	l := launcher.New(client, testConfig(), time.Millisecond, 3)

	succeeded, failed, err := l.Handle(context.Background(), testRequest())
	if err != nil {
		t.Fatalf("expected no error (NotFound mid-poll is a terminal outcome, not an infra error), got %v", err)
	}
	if succeeded != nil {
		t.Fatalf("expected no HealthCheckSucceeded, got %+v", succeeded)
	}
	if failed == nil {
		t.Fatal("expected HealthCheckExhausted")
	}
	if failed.DeployAttemptID != testRequest().DeployAttemptID {
		t.Fatalf("got deployAttemptId %q", failed.DeployAttemptID)
	}
}

// Un tenant creado con una versión anterior del chart tiene que adoptar la
// nueva en su siguiente deploy. El chart es genérico y evoluciona (soporte de
// base de datos, por ejemplo); si el update sólo tocara los values, los
// tenants ya existentes se quedarían renderizando la versión con la que
// nacieron, para siempre y sin que nada fallara.
func TestHandleMovesAnExistingApplicationToTheConfiguredChartVersion(t *testing.T) {
	scheme := runtime.NewScheme()
	existing := &unstructured.Unstructured{Object: map[string]any{
		"apiVersion": "argoproj.io/v1alpha1",
		"kind":       "Application",
		"metadata": map[string]any{
			"name":      "tenant-backend-abc12345",
			"namespace": "argocd",
		},
		"spec": map[string]any{
			"source": map[string]any{
				"repoURL":        "registry.apperture.dev/charts",
				"chart":          "podium-app",
				"targetRevision": "0.1.2",
				"helm": map[string]any{
					"valuesObject": map[string]any{"hash": "old-hash"},
				},
			},
		},
		"status": map[string]any{"health": map[string]any{"status": "Healthy"}},
	}}
	client := dynamicfake.NewSimpleDynamicClientWithCustomListKinds(scheme, map[schema.GroupVersionResource]string{
		launcher.ApplicationGVR: "ApplicationList",
	}, existing)

	if _, _, err := launcher.New(client, testConfig(), time.Millisecond, 5).Handle(context.Background(), testRequest()); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	got, err := client.Resource(launcher.ApplicationGVR).Namespace("argocd").Get(context.Background(), "tenant-backend-abc12345", metav1.GetOptions{})
	if err != nil {
		t.Fatalf("unexpected error reading back application: %v", err)
	}
	revision, _, _ := unstructured.NestedString(got.Object, "spec", "source", "targetRevision")
	if revision != testConfig().ChartVersion {
		t.Fatalf("got targetRevision %q, want %q", revision, testConfig().ChartVersion)
	}
}
