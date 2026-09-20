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

// Un Project puede declarar varios servicios en el mismo podium.yaml — todos
// comparten hash (mismo Project), así que el nombre de la Application (y por
// tanto el release de Helm, y los nombres de Deployment/Service/Ingress que
// de ahí salen) tiene que llevar también el serviceName, o dos servicios del
// mismo repo se pisarían el uno al otro.
func TestBuildNamesTheApplicationAfterServiceNameAndHash(t *testing.T) {
	app := argospec.Build(testRequest(), testConfig())

	if app.GetName() != "tenant-backend-abc12345" {
		t.Fatalf("got name %q, want tenant-backend-abc12345", app.GetName())
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
	// {serviceName}-{hash}, no solo {hash}: mismo motivo que el nombre de la
	// Application — dos servicios del mismo Project comparten hash, y el
	// wildcard *.apperture.dev solo cubre un nivel (confirmado contra el
	// cert real), así que no se puede resolver con un subdominio
	// "serviceName.hash.apperture.dev".
	if ingress["host"] != "backend-abc12345.apperture.dev" {
		t.Fatalf("got values.ingress.host %v, want backend-abc12345.apperture.dev", ingress["host"])
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

// Sin declaración de base de datos, el chart tiene que recibir mode "none":
// es lo que deja el Deployment exactamente como antes de que el chart supiera
// nada de bases de datos, y es el caso de la inmensa mayoría de servicios.
func TestBuildDefaultsDatabaseToNone(t *testing.T) {
	values := argospec.ValuesObject(testRequest(), testConfig())

	database, ok := values["database"].(map[string]any)
	if !ok {
		t.Fatalf("values[database] no es un mapa: %#v", values["database"])
	}
	if database["mode"] != "none" {
		t.Fatalf("got mode %q, want none", database["mode"])
	}
}

// El lanzador no decide el modo ni interpreta el podium.yaml: eso ya viene
// resuelto por el BC Deploy. Aquí solo se comprueba que lo copia tal cual.
func TestBuildCopiesTheResolvedUrlDatabase(t *testing.T) {
	request := testRequest()
	request.Database = events.DatabaseDeclaration{Mode: "url", UrlVar: "DB_URL"}

	values := argospec.ValuesObject(request, testConfig())
	database := values["database"].(map[string]any)

	if database["mode"] != "url" {
		t.Fatalf("got mode %q, want url", database["mode"])
	}
	if database["urlVar"] != "DB_URL" {
		t.Fatalf("got urlVar %q, want DB_URL", database["urlVar"])
	}
}

func TestBuildCopiesTheResolvedPartsDatabase(t *testing.T) {
	request := testRequest()
	request.Database = events.DatabaseDeclaration{
		Mode: "parts",
		Vars: events.FlexibleMap{"dbname": "APP_DATABASE", "username": "APP_DATABASE_USER"},
	}

	values := argospec.ValuesObject(request, testConfig())
	database := values["database"].(map[string]any)

	if database["mode"] != "parts" {
		t.Fatalf("got mode %q, want parts", database["mode"])
	}

	// map[string]any y no map[string]string: unstructured.SetNestedMap sólo
	// acepta tipos JSON, y un map[string]string revienta al construir la
	// Application.
	vars, ok := database["vars"].(map[string]any)
	if !ok {
		t.Fatalf("values[database][vars] no es map[string]any: %#v", database["vars"])
	}
	if vars["dbname"] != "APP_DATABASE" {
		t.Fatalf("got dbname %q, want APP_DATABASE", vars["dbname"])
	}
}
