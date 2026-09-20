// Package jobspec maps a BuildJobRequested event into the Kubernetes Job
// that actually runs services/build-runner — pure data mapping, no
// Kubernetes client involved, so it's fully unit-testable on its own.
package jobspec

import (
	"crypto/sha256"
	"encoding/hex"
	"sort"

	batchv1 "k8s.io/api/batch/v1"
	corev1 "k8s.io/api/core/v1"
	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
	"k8s.io/utils/ptr"

	"github.com/Apperture-Dev/podium/services/build-launcher/internal/events"
)

// Config is the launcher's own infrastructure configuration — never part
// of the domain event (see BuildJobRequested's docs: IMAGE_REGISTRY is
// "configuración de infraestructura, no de dominio").
type Config struct {
	Namespace               string
	ImageRegistry           string
	RegistrySecretName      string
	TTLSecondsAfterFinished int32
}

const registryAuthMountPath = "/var/run/secrets/registry"

// Build returns the Job that runs req.JobImage with req.EnvVars plus the
// launcher's own IMAGE_REGISTRY, mounting cfg.RegistrySecretName so
// `buildah push` can authenticate.
func Build(req events.BuildJobRequested, cfg Config) *batchv1.Job {
	backoffLimit := int32(0)
	ttl := cfg.TTLSecondsAfterFinished

	return &batchv1.Job{
		ObjectMeta: metav1.ObjectMeta{
			Name:      jobName(req.BuildJobID),
			Namespace: cfg.Namespace,
			Labels: map[string]string{
				"podium.dev/component":    "build-job",
				"podium.dev/build-job-id": req.BuildJobID,
			},
		},
		Spec: batchv1.JobSpec{
			TTLSecondsAfterFinished: &ttl,
			BackoffLimit:            &backoffLimit,
			Template: corev1.PodTemplateSpec{
				ObjectMeta: metav1.ObjectMeta{
					Labels: map[string]string{
						"podium.dev/component":    "build-job",
						"podium.dev/build-job-id": req.BuildJobID,
					},
				},
				Spec: corev1.PodSpec{
					RestartPolicy: corev1.RestartPolicyNever,
					// req.JobImage vive en el registro privado de GitLab (mismo
					// registro que el resto de imágenes de este repo) — sin esto
					// el kubelet no puede tirar de ella (ImagePullBackOff). Fijo,
					// no viene de Config: es el mismo secret que ya usa cualquier
					// otro Deployment/CronJob de este clúster, nunca varía.
					ImagePullSecrets: []corev1.LocalObjectReference{
						{Name: "gitlab-token-auth"},
					},
					Containers: []corev1.Container{
						{
							Name:    "build",
							Image:   req.JobImage,
							Command: commandOrNil(req.Command),
							Env:     env(req.EnvVars, cfg),
							VolumeMounts: []corev1.VolumeMount{
								{Name: "registry-auth", MountPath: registryAuthMountPath, ReadOnly: true},
							},
							// buildah necesita montar overlayfs para construir capas —
							// en un pod sin privilegios revienta con "failed to make
							// mount private: permission denied" (visto contra el
							// clúster real). Mismo motivo por el que .buildah-base en
							// .gitlab-ci.yml corre en un runner con acceso elevado.
							SecurityContext: &corev1.SecurityContext{
								Privileged: ptr.To(true),
							},
						},
					},
					Volumes: []corev1.Volume{
						{
							Name: "registry-auth",
							VolumeSource: corev1.VolumeSource{
								Secret: &corev1.SecretVolumeSource{SecretName: cfg.RegistrySecretName},
							},
						},
					},
				},
			},
		},
	}
}

func commandOrNil(command []string) []string {
	if len(command) == 0 {
		return nil
	}
	return command
}

func env(vars map[string]string, cfg Config) []corev1.EnvVar {
	keys := make([]string, 0, len(vars))
	for k := range vars {
		keys = append(keys, k)
	}
	sort.Strings(keys)

	out := make([]corev1.EnvVar, 0, len(keys)+2)
	for _, k := range keys {
		out = append(out, corev1.EnvVar{Name: k, Value: vars[k]})
	}
	out = append(out,
		corev1.EnvVar{Name: "IMAGE_REGISTRY", Value: cfg.ImageRegistry},
		corev1.EnvVar{Name: "REGISTRY_AUTH_FILE", Value: registryAuthMountPath + "/.dockerconfigjson"},
	)
	return out
}

// jobName derives a deterministic, DNS-1123-safe Job name from a BuildJob's
// id — ids may be ULIDs/UUIDs with characters or lengths that don't fit a
// Kubernetes object name directly, so this hashes rather than truncates
// (truncating risks collisions between two ids sharing a long prefix).
func jobName(buildJobID string) string {
	sum := sha256.Sum256([]byte(buildJobID))
	return "build-" + hex.EncodeToString(sum[:])[:16]
}
