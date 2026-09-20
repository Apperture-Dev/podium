// Package argospec maps a DeployAttemptRequested event into the ArgoCD
// Application that actually deploys the tenant's app via the generic
// "podium-app" Helm chart — pure data mapping, no Kubernetes client
// involved, so it's fully unit-testable on its own (mirrors
// services/build-launcher/internal/jobspec).
package argospec

import (
	"strings"

	"k8s.io/apimachinery/pkg/apis/meta/v1/unstructured"

	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/events"
)

// Config is the launcher's own infrastructure configuration — never part
// of the domain event (same criterion as IMAGE_REGISTRY in build-launcher:
// which chart/registry/version to use is infrastructure, decided here, not
// carried on DeployAttemptRequested).
type Config struct {
	ArgoCDNamespace string
	ChartRepoURL    string
	ChartName       string
	ChartVersion    string
	BaseDomain      string
}

// Build returns the ArgoCD Application (as unstructured data — Application
// is a CRD, not a built-in client-go type) for req, ready to Create or to
// source a values patch from on Update.
func Build(req events.DeployAttemptRequested, cfg Config) *unstructured.Unstructured {
	repository, tag := splitImage(req.Image)

	return &unstructured.Unstructured{
		Object: map[string]any{
			"apiVersion": "argoproj.io/v1alpha1",
			"kind":       "Application",
			"metadata": map[string]any{
				"name":      "tenant-" + tenantSlug(req),
				"namespace": cfg.ArgoCDNamespace,
			},
			"spec": map[string]any{
				"project": "default",
				"source": map[string]any{
					"repoURL":        cfg.ChartRepoURL,
					"chart":          cfg.ChartName,
					"targetRevision": cfg.ChartVersion,
					"helm": map[string]any{
						"valuesObject": valuesObject(req, cfg, repository, tag),
					},
				},
				"destination": map[string]any{
					"server":    "https://kubernetes.default.svc",
					"namespace": req.Hash,
				},
				"syncPolicy": map[string]any{
					"automated": map[string]any{
						"prune":    true,
						"selfHeal": true,
					},
					"syncOptions": []any{"CreateNamespace=true"},
				},
			},
		},
	}
}

// ValuesObject returns just spec.source.helm.valuesObject for req/cfg —
// exported so Launcher can patch only this field onto an Application that
// already exists, without touching the rest of the object.
func ValuesObject(req events.DeployAttemptRequested, cfg Config) map[string]any {
	repository, tag := splitImage(req.Image)
	return valuesObject(req, cfg, repository, tag)
}

func valuesObject(req events.DeployAttemptRequested, cfg Config, repository, tag string) map[string]any {
	return map[string]any{
		"hash": req.Hash,
		"image": map[string]any{
			"repository": repository,
			"tag":        tag,
		},
		"ingress": map[string]any{
			"host": tenantSlug(req) + "." + cfg.BaseDomain,
		},
		"port": req.Port,
	}
}

// tenantSlug identifica un servicio dentro de un Project — un Project puede
// declarar varios servicios en el mismo podium.yaml, todos con el mismo
// Hash, así que el hash solo no basta para nombrar ni la Application ni el
// host público sin que dos servicios del mismo repo se pisen entre sí.
// {serviceName}-{hash}, no un subdominio "{serviceName}.{hash}": el
// wildcard apperture-wildcard-tls solo cubre *.apperture.dev (un nivel),
// confirmado contra el certificado real.
func tenantSlug(req events.DeployAttemptRequested) string {
	return req.ServiceName + "-" + req.Hash
}

// splitImage splits "repo/path:tag" on the LAST colon — a registry
// repository can itself contain a colon (a port, e.g.
// "registry.apperture.dev:443/team-a/backend:v1"), so strings.Split would
// break on that; only the final colon separates the tag.
func splitImage(image string) (repository, tag string) {
	idx := strings.LastIndex(image, ":")
	if idx == -1 {
		return image, ""
	}
	return image[:idx], image[idx+1:]
}
