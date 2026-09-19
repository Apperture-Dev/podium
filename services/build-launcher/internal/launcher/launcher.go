// Package launcher orchestrates one BuildJobRequested: create the Job,
// wait for it to reach a terminal state, and produce exactly one of
// JobSucceeded/JobFailed — no independent logic beyond that wiring (the
// mapping lives in jobspec, this package only sequences the calls).
package launcher

import (
	"context"
	"fmt"
	"time"

	batchv1 "k8s.io/api/batch/v1"
	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
	"k8s.io/client-go/kubernetes"

	"github.com/Apperture-Dev/podium/services/build-launcher/internal/events"
	"github.com/Apperture-Dev/podium/services/build-launcher/internal/jobspec"
)

// Launcher creates and watches build Jobs.
type Launcher struct {
	client       kubernetes.Interface
	cfg          jobspec.Config
	pollInterval time.Duration
}

func New(client kubernetes.Interface, cfg jobspec.Config, pollInterval time.Duration) *Launcher {
	return &Launcher{client: client, cfg: cfg, pollInterval: pollInterval}
}

// Handle creates the Job for req, blocks until it reaches a terminal
// state, and returns exactly one of (succeeded, failed) — never both, and
// never neither, unless ctx is cancelled or the Kubernetes API itself
// errors (err != nil).
func (l *Launcher) Handle(ctx context.Context, req events.BuildJobRequested) (succeeded *events.JobSucceeded, failed *events.JobFailed, err error) {
	image, err := deriveImage(req.EnvVars, l.cfg.ImageRegistry)
	if err != nil {
		return nil, nil, err
	}

	job := jobspec.Build(req, l.cfg)
	if _, err := l.client.BatchV1().Jobs(l.cfg.Namespace).Create(ctx, job, metav1.CreateOptions{}); err != nil {
		return nil, nil, fmt.Errorf("create job: %w", err)
	}

	final, err := l.waitForCompletion(ctx, l.cfg.Namespace, job.Name)
	if err != nil {
		return nil, nil, err
	}

	if final.Status.Succeeded > 0 {
		return &events.JobSucceeded{
			BuildJobID:          req.BuildJobID,
			Image:               image,
			BuildEnvVars:        map[string]string{},
			DeployEnvVars:       map[string]string{},
			DatabaseDeclaration: map[string]any{},
		}, nil, nil
	}

	return nil, &events.JobFailed{
		BuildJobID:   req.BuildJobID,
		ErrorMessage: failureMessage(final),
	}, nil
}

func (l *Launcher) waitForCompletion(ctx context.Context, namespace, name string) (*batchv1.Job, error) {
	ticker := time.NewTicker(l.pollInterval)
	defer ticker.Stop()

	for {
		job, err := l.client.BatchV1().Jobs(namespace).Get(ctx, name, metav1.GetOptions{})
		if err != nil {
			return nil, fmt.Errorf("get job %s/%s: %w", namespace, name, err)
		}
		if job.Status.Succeeded > 0 || job.Status.Failed > 0 {
			return job, nil
		}

		select {
		case <-ctx.Done():
			return nil, ctx.Err()
		case <-ticker.C:
		}
	}
}

func failureMessage(job *batchv1.Job) string {
	for _, c := range job.Status.Conditions {
		if c.Type == batchv1.JobFailed && c.Message != "" {
			return c.Message
		}
	}
	return "build job failed without a specific condition message"
}

// deriveImage mirrors services/build-runner/entrypoint.sh's own tag
// convention exactly — the launcher doesn't invent a new naming scheme, it
// reuses the one the Job itself already builds and pushes with.
func deriveImage(envVars map[string]string, imageRegistry string) (string, error) {
	projectID, serviceName, version := envVars["PROJECT_ID"], envVars["SERVICE_NAME"], envVars["VERSION"]
	if projectID == "" || serviceName == "" || version == "" {
		return "", fmt.Errorf("missing PROJECT_ID/SERVICE_NAME/VERSION in envVars: %v", envVars)
	}
	return fmt.Sprintf("%s/%s-%s:%s", imageRegistry, projectID, serviceName, version), nil
}
