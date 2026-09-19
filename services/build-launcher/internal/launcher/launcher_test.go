package launcher_test

import (
	"context"
	"testing"
	"time"

	batchv1 "k8s.io/api/batch/v1"
	metav1 "k8s.io/apimachinery/pkg/apis/meta/v1"
	"k8s.io/apimachinery/pkg/runtime"
	fakeclient "k8s.io/client-go/kubernetes/fake"
	k8stesting "k8s.io/client-go/testing"

	"github.com/Apperture-Dev/podium/services/build-launcher/internal/events"
	"github.com/Apperture-Dev/podium/services/build-launcher/internal/jobspec"
	"github.com/Apperture-Dev/podium/services/build-launcher/internal/launcher"
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
			"SERVICE_NAME": "app",
			"PROJECT_ID":   "team-a-project",
			"VERSION":      "abc1234",
		},
	}
}

// withGetSequence makes successive "get jobs" calls return each status in
// order, then keeps returning the last one — simulates polling a Job that
// starts Pending and eventually reaches a terminal state, without any
// real waiting or goroutines.
func withGetSequence(client *fakeclient.Clientset, statuses []batchv1.JobStatus) {
	call := 0
	client.PrependReactor("get", "jobs", func(action k8stesting.Action) (bool, runtime.Object, error) {
		getAction := action.(k8stesting.GetAction)
		status := statuses[call]
		if call < len(statuses)-1 {
			call++
		}
		return true, &batchv1.Job{
			ObjectMeta: metav1.ObjectMeta{Name: getAction.GetName(), Namespace: getAction.GetNamespace()},
			Status:     status,
		}, nil
	})
}

func TestHandleCreatesTheJobFromTheRequest(t *testing.T) {
	client := fakeclient.NewSimpleClientset()
	withGetSequence(client, []batchv1.JobStatus{{Succeeded: 1}})
	l := launcher.New(client, testConfig(), time.Millisecond)

	_, _, err := l.Handle(context.Background(), testRequest())
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	jobs, err := client.BatchV1().Jobs("podium-build").List(context.Background(), metav1.ListOptions{})
	if err != nil {
		t.Fatalf("unexpected error listing jobs: %v", err)
	}
	if len(jobs.Items) != 1 {
		t.Fatalf("expected exactly 1 Job created, got %d", len(jobs.Items))
	}
}

func TestHandleReturnsJobSucceededWhenTheJobCompletes(t *testing.T) {
	client := fakeclient.NewSimpleClientset()
	withGetSequence(client, []batchv1.JobStatus{{}, {}, {Succeeded: 1}})
	l := launcher.New(client, testConfig(), time.Millisecond)

	succeeded, failed, err := l.Handle(context.Background(), testRequest())
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if failed != nil {
		t.Fatalf("expected no JobFailed, got %+v", failed)
	}
	if succeeded == nil {
		t.Fatal("expected a JobSucceeded result")
	}
	if succeeded.BuildJobID != testRequest().BuildJobID {
		t.Fatalf("got buildJobId %q", succeeded.BuildJobID)
	}
	wantImage := "registry.apperture.dev/team-a-project-app:abc1234"
	if succeeded.Image != wantImage {
		t.Fatalf("got image %q, want %q", succeeded.Image, wantImage)
	}
}

func TestHandleReturnsJobFailedWhenTheJobFails(t *testing.T) {
	client := fakeclient.NewSimpleClientset()
	withGetSequence(client, []batchv1.JobStatus{{Failed: 1, Conditions: []batchv1.JobCondition{
		{Type: batchv1.JobFailed, Status: "True", Message: "container exited with code 1"},
	}}})
	l := launcher.New(client, testConfig(), time.Millisecond)

	succeeded, failed, err := l.Handle(context.Background(), testRequest())
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if succeeded != nil {
		t.Fatalf("expected no JobSucceeded, got %+v", succeeded)
	}
	if failed == nil {
		t.Fatal("expected a JobFailed result")
	}
	if failed.ErrorMessage != "container exited with code 1" {
		t.Fatalf("got errorMessage %q", failed.ErrorMessage)
	}
}

func TestHandleFailsFastIfTheJobIDIsMissingEnvVars(t *testing.T) {
	client := fakeclient.NewSimpleClientset()
	l := launcher.New(client, testConfig(), time.Millisecond)

	req := events.BuildJobRequested{BuildJobID: "x", JobImage: "img", EnvVars: map[string]string{}}
	_, _, err := l.Handle(context.Background(), req)
	if err == nil {
		t.Fatal("expected an error when SERVICE_NAME/PROJECT_ID/VERSION are missing")
	}
}
