package proxy_test

import (
	"context"
	"errors"
	"net/http"
	"net/http/httptest"
	"testing"
	"time"

	"github.com/Apperture-Dev/podium/services/activator/internal/lastseen"
	"github.com/Apperture-Dev/podium/services/activator/internal/proxy"
	"github.com/Apperture-Dev/podium/services/activator/internal/routing"
	"github.com/Apperture-Dev/podium/services/activator/internal/wake"
)

func newHandler(t *testing.T, ready bool, readyErr error, wakeErr error) (*proxy.Handler, *routing.Table, *int) {
	t.Helper()
	table := routing.NewTable()
	table.Set(routing.Entry{Host: "team-a.apperture.dev", Namespace: "team-a", DeploymentName: "app", ServiceName: "app", ServicePort: 80})

	backendCalls := 0
	h := &proxy.Handler{
		Table: table,
		Seen:  lastseen.NewTracker(),
		Waker: wake.New(func(ctx context.Context, host string) error { return wakeErr }),
		Ready: func(ctx context.Context, e routing.Entry) (bool, error) { return ready, readyErr },
		Backend: func(e routing.Entry) http.Handler {
			return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				backendCalls++
				w.WriteHeader(http.StatusOK)
			})
		},
	}
	return h, table, &backendCalls
}

func TestServeHTTPReturns404ForUnknownHost(t *testing.T) {
	h, _, _ := newHandler(t, true, nil, nil)

	req := httptest.NewRequest(http.MethodGet, "http://unknown.apperture.dev/", nil)
	req.Host = "unknown.apperture.dev"
	rec := httptest.NewRecorder()

	h.ServeHTTP(rec, req)

	if rec.Code != http.StatusNotFound {
		t.Fatalf("expected 404 for an unknown host, got %d", rec.Code)
	}
}

func TestServeHTTPProxiesDirectlyWhenAlreadyReady(t *testing.T) {
	h, _, backendCalls := newHandler(t, true, nil, nil)

	req := httptest.NewRequest(http.MethodGet, "http://team-a.apperture.dev/", nil)
	req.Host = "team-a.apperture.dev"
	rec := httptest.NewRecorder()

	h.ServeHTTP(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200, got %d", rec.Code)
	}
	if *backendCalls != 1 {
		t.Fatalf("expected the backend to be called once, got %d", *backendCalls)
	}
}

func TestServeHTTPTouchesLastSeenForKnownHost(t *testing.T) {
	h, _, _ := newHandler(t, true, nil, nil)

	req := httptest.NewRequest(http.MethodGet, "http://team-a.apperture.dev/", nil)
	req.Host = "team-a.apperture.dev"
	h.ServeHTTP(httptest.NewRecorder(), req)

	if h.Seen.Idle("team-a.apperture.dev", time.Hour) {
		t.Fatal("expected the host to be marked as seen (not idle) after a request")
	}
}

func TestServeHTTPWakesThenProxiesWhenNotReady(t *testing.T) {
	h, _, backendCalls := newHandler(t, false, nil, nil)

	req := httptest.NewRequest(http.MethodGet, "http://team-a.apperture.dev/", nil)
	req.Host = "team-a.apperture.dev"
	rec := httptest.NewRecorder()

	h.ServeHTTP(rec, req)

	if rec.Code != http.StatusOK {
		t.Fatalf("expected 200 after a successful wake, got %d", rec.Code)
	}
	if *backendCalls != 1 {
		t.Fatalf("expected the backend to be called once after waking, got %d", *backendCalls)
	}
}

func TestServeHTTPReturns502WhenWakeFails(t *testing.T) {
	h, _, backendCalls := newHandler(t, false, nil, errors.New("scale failed"))

	req := httptest.NewRequest(http.MethodGet, "http://team-a.apperture.dev/", nil)
	req.Host = "team-a.apperture.dev"
	rec := httptest.NewRecorder()

	h.ServeHTTP(rec, req)

	if rec.Code != http.StatusBadGateway {
		t.Fatalf("expected 502 when waking fails, got %d", rec.Code)
	}
	if *backendCalls != 0 {
		t.Fatalf("expected the backend to never be called when waking fails, got %d calls", *backendCalls)
	}
}

func TestServeHTTPReturns502WhenReadyCheckErrors(t *testing.T) {
	h, _, backendCalls := newHandler(t, false, errors.New("api server unreachable"), nil)

	req := httptest.NewRequest(http.MethodGet, "http://team-a.apperture.dev/", nil)
	req.Host = "team-a.apperture.dev"
	rec := httptest.NewRecorder()

	h.ServeHTTP(rec, req)

	if rec.Code != http.StatusBadGateway {
		t.Fatalf("expected 502 when the readiness check itself errors, got %d", rec.Code)
	}
	if *backendCalls != 0 {
		t.Fatalf("expected the backend to never be called, got %d calls", *backendCalls)
	}
}
