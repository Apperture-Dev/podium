package argospec_test

import (
	"testing"

	"k8s.io/apimachinery/pkg/apis/meta/v1/unstructured"

	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/argospec"
	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/events"
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
		Port:            8080,
	}
}

func TestBuildSetsApiVersionAndKind(t *testing.T) {
	app := argospec.Build(testRequest(), testConfig())

	if app.GetAPIVersion() != "argoproj.io/v1alpha1" {
		t.Fatalf("got apiVersion %q", app.GetAPIVersion())
	}
	if app.GetKind() != "Application" {
		t.Fatalf("got kind %q", app.GetKind())
	}
}

func TestBuildNamesTheApplicationAfterTheHash(t *testing.T) {
	app := argospec.Build(testRequest(), testConfig())

	if app.GetName() != "tenant-abc12345" {
		t.Fatalf("got name %q, want tenant-abc12345", app.GetName())
	}
	if app.GetNamespace() != "argocd" {
		t.Fatalf("got namespace %q, want argocd", app.GetNamespace())
	}
}

func TestBuildSetsChartSource(t *testing.T) {
	app := argospec.Build(testRequest(), testConfig())

	repoURL, _, _ := unstructured.NestedString(app.Object, "spec", "source", "repoURL")
	chart, _, _ := unstructured.NestedString(app.Object, "spec", "source", "chart")
	targetRevision, _, _ := unstructured.NestedString(app.Object, "spec", "source", "targetRevision")

	if repoURL != "registry.apperture.dev/charts" {
		t.Fatalf("got repoURL %q", repoURL)
	}
	if chart != "podium-app" {
		t.Fatalf("got chart %q", chart)
	}
	if targetRevision != "0.1.0" {
		t.Fatalf("got targetRevision %q", targetRevision)
	}
}

func TestBuildSetsValuesObjectFromTheRequest(t *testing.T) {
	app := argospec.Build(testRequest(), testConfig())

	values, found, err := unstructured.NestedMap(app.Object, "spec", "source", "helm", "valuesObject")
	if err != nil || !found {
		t.Fatalf("expected spec.source.helm.valuesObject to be set, err=%v found=%v", err, found)
	}

	if values["hash"] != "abc12345" {
		t.Fatalf("got values.hash %v", values["hash"])
	}
	image, ok := values["image"].(map[string]any)
	if !ok {
		t.Fatalf("expected values.image to be a map, got %T", values["image"])
	}
	if image["repository"] != "registry.apperture.dev/team-a-backend" {
		t.Fatalf("got values.image.repository %v", image["repository"])
	}
	if image["tag"] != "v1" {
		t.Fatalf("got values.image.tag %v", image["tag"])
	}
	ingress, ok := values["ingress"].(map[string]any)
	if !ok {
		t.Fatalf("expected values.ingress to be a map, got %T", values["ingress"])
	}
	if ingress["host"] != "abc12345.apperture.dev" {
		t.Fatalf("got values.ingress.host %v", ingress["host"])
	}
}

// El puerto viaja en DeployAttemptRequested (con origen en
// Template.defaultPort, convención por lenguaje/framework — ver
// services/php/src/Build/Domain/Template.php), no en config propia del
// lanzador.
func TestBuildUsesThePortFromTheRequest(t *testing.T) {
	app := argospec.Build(testRequest(), testConfig())

	values, _, _ := unstructured.NestedMap(app.Object, "spec", "source", "helm", "valuesObject")

	if values["port"] != int64(8080) {
		t.Fatalf("got values.port %v (%T), want 8080", values["port"], values["port"])
	}
}

func TestBuildSplitsImageOnTheLastColonNotEveryColon(t *testing.T) {
	req := testRequest()
	req.Image = "registry.apperture.dev:443/team-a/backend:v1"
	app := argospec.Build(req, testConfig())

	values, _, _ := unstructured.NestedMap(app.Object, "spec", "source", "helm", "valuesObject")
	image := values["image"].(map[string]any)

	if image["repository"] != "registry.apperture.dev:443/team-a/backend" {
		t.Fatalf("got repository %v", image["repository"])
	}
	if image["tag"] != "v1" {
		t.Fatalf("got tag %v", image["tag"])
	}
}

func TestBuildSetsDestinationAndSyncPolicy(t *testing.T) {
	app := argospec.Build(testRequest(), testConfig())

	destNamespace, _, _ := unstructured.NestedString(app.Object, "spec", "destination", "namespace")
	if destNamespace != "abc12345" {
		t.Fatalf("got destination.namespace %q, want abc12345 (the tenant's own namespace)", destNamespace)
	}

	prune, _, _ := unstructured.NestedBool(app.Object, "spec", "syncPolicy", "automated", "prune")
	selfHeal, _, _ := unstructured.NestedBool(app.Object, "spec", "syncPolicy", "automated", "selfHeal")
	if !prune || !selfHeal {
		t.Fatalf("expected automated prune+selfHeal, got prune=%v selfHeal=%v", prune, selfHeal)
	}

	syncOptions, _, _ := unstructured.NestedStringSlice(app.Object, "spec", "syncPolicy", "syncOptions")
	if len(syncOptions) != 1 || syncOptions[0] != "CreateNamespace=true" {
		t.Fatalf("got syncOptions %v", syncOptions)
	}
}

func TestValuesObjectIsDeterministicForRepeatedCalls(t *testing.T) {
	first := argospec.Build(testRequest(), testConfig())
	second := argospec.Build(testRequest(), testConfig())

	v1, _, _ := unstructured.NestedMap(first.Object, "spec", "source", "helm", "valuesObject")
	v2, _, _ := unstructured.NestedMap(second.Object, "spec", "source", "helm", "valuesObject")

	if v1["hash"] != v2["hash"] {
		t.Fatalf("expected the same request to always produce the same values, got %v and %v", v1, v2)
	}
}
