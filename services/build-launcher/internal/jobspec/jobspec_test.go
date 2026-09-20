package jobspec_test

import (
	"testing"

	"github.com/Apperture-Dev/podium/services/build-launcher/internal/events"
	"github.com/Apperture-Dev/podium/services/build-launcher/internal/jobspec"
)

func testConfig() jobspec.Config {
	return jobspec.Config{
		Namespace:               "podium-build",
		ImageRegistry:           "registry.apperture.dev",
		RegistrySecretName:      "zot-pull-secret",
		TTLSecondsAfterFinished: 300,
	}
}

func testRequest() events.BuildJobRequested {
	return events.BuildJobRequested{
		BuildJobID: "01HXYZ0123456789ABCDEFGHJK",
		JobImage:   "registry.gitlab.com/apperturedev/podium/build-runner:latest",
		Command:    []string{},
		EnvVars: map[string]string{
			"SERVICE_NAME":   "app",
			"PROJECT_ID":     "team-a-project",
			"VERSION":        "abc1234",
			"COMMIT_ID":      "abc1234def",
			"REPOSITORY_URL": "https://github.com/team-a/app",
		},
	}
}

func TestBuildSetsNamespaceAndImage(t *testing.T) {
	job := jobspec.Build(testRequest(), testConfig())

	if job.Namespace != "podium-build" {
		t.Fatalf("got namespace %q, want podium-build", job.Namespace)
	}
	containers := job.Spec.Template.Spec.Containers
	if len(containers) != 1 {
		t.Fatalf("expected exactly 1 container, got %d", len(containers))
	}
	if containers[0].Image != "registry.gitlab.com/apperturedev/podium/build-runner:latest" {
		t.Fatalf("got image %q", containers[0].Image)
	}
}

func TestBuildNameIsDeterministicAndDNSValid(t *testing.T) {
	job := jobspec.Build(testRequest(), testConfig())

	if job.Name == "" {
		t.Fatal("expected a non-empty Job name")
	}
	if len(job.Name) > 63 {
		t.Fatalf("Job name %q exceeds 63 chars (%d)", job.Name, len(job.Name))
	}
	for _, r := range job.Name {
		isLower := r >= 'a' && r <= 'z'
		isDigit := r >= '0' && r <= '9'
		isDash := r == '-'
		if !isLower && !isDigit && !isDash {
			t.Fatalf("Job name %q contains an invalid DNS-1123 character %q", job.Name, r)
		}
	}

	again := jobspec.Build(testRequest(), testConfig())
	if again.Name != job.Name {
		t.Fatalf("expected the same request to always produce the same Job name, got %q and %q", job.Name, again.Name)
	}
}

func TestBuildSetsTrackingLabels(t *testing.T) {
	req := testRequest()
	job := jobspec.Build(req, testConfig())

	if job.Labels["podium.dev/component"] != "build-job" {
		t.Fatalf("got podium.dev/component=%q, want build-job", job.Labels["podium.dev/component"])
	}
	if job.Labels["podium.dev/build-job-id"] != req.BuildJobID {
		t.Fatalf("got podium.dev/build-job-id=%q, want %q", job.Labels["podium.dev/build-job-id"], req.BuildJobID)
	}
}

func TestBuildPassesThroughEnvVarsPlusImageRegistry(t *testing.T) {
	req := testRequest()
	job := jobspec.Build(req, testConfig())

	env := map[string]string{}
	for _, e := range job.Spec.Template.Spec.Containers[0].Env {
		env[e.Name] = e.Value
	}

	for k, v := range req.EnvVars {
		if env[k] != v {
			t.Fatalf("expected env %s=%s, got %s", k, v, env[k])
		}
	}
	if env["IMAGE_REGISTRY"] != "registry.apperture.dev" {
		t.Fatalf("expected IMAGE_REGISTRY from config, got %q", env["IMAGE_REGISTRY"])
	}
}

func TestBuildMountsTheRegistrySecretForPush(t *testing.T) {
	job := jobspec.Build(testRequest(), testConfig())

	container := job.Spec.Template.Spec.Containers[0]
	var mountPath string
	for _, m := range container.VolumeMounts {
		if m.Name == "registry-auth" {
			mountPath = m.MountPath
		}
	}
	if mountPath == "" {
		t.Fatal("expected a \"registry-auth\" volume mount for the registry credentials")
	}

	var foundVolume bool
	for _, v := range job.Spec.Template.Spec.Volumes {
		if v.Name == "registry-auth" && v.Secret != nil && v.Secret.SecretName == "zot-pull-secret" {
			foundVolume = true
		}
	}
	if !foundVolume {
		t.Fatal("expected a volume named \"registry-auth\" backed by the configured registry secret")
	}

	var authFileEnv string
	for _, e := range container.Env {
		if e.Name == "REGISTRY_AUTH_FILE" {
			authFileEnv = e.Value
		}
	}
	if authFileEnv == "" {
		t.Fatal("expected REGISTRY_AUTH_FILE to point at the mounted secret")
	}
}

func TestBuildPullsTheJobImageWithTheGitLabRegistrySecret(t *testing.T) {
	job := jobspec.Build(testRequest(), testConfig())

	secrets := job.Spec.Template.Spec.ImagePullSecrets
	if len(secrets) != 1 || secrets[0].Name != "gitlab-token-auth" {
		t.Fatalf("expected exactly one imagePullSecret named gitlab-token-auth, got %v", secrets)
	}
}

func TestBuildSetsTTLAndNeverRestarts(t *testing.T) {
	job := jobspec.Build(testRequest(), testConfig())

	if job.Spec.TTLSecondsAfterFinished == nil || *job.Spec.TTLSecondsAfterFinished != 300 {
		t.Fatalf("expected TTLSecondsAfterFinished=300, got %v", job.Spec.TTLSecondsAfterFinished)
	}
	if job.Spec.Template.Spec.RestartPolicy != "Never" {
		t.Fatalf("expected RestartPolicy=Never, got %q", job.Spec.Template.Spec.RestartPolicy)
	}
}
