// Package proxy is the activator's HTTP entry point: for each request it
// resolves the Host header against the routing table, wakes the tenant app
// if it isn't ready yet, and reverse-proxies once it is.
package proxy

import (
	"context"
	"net/http"

	"github.com/Apperture-Dev/podium/services/activator/internal/lastseen"
	"github.com/Apperture-Dev/podium/services/activator/internal/routing"
	"github.com/Apperture-Dev/podium/services/activator/internal/wake"
)

// Handler is an http.Handler that wakes-and-proxies requests for known
// tenant hosts.
type Handler struct {
	Table *routing.Table
	Seen  *lastseen.Tracker
	Waker *wake.Waker

	// Ready reports whether entry's app can already serve traffic.
	Ready func(ctx context.Context, entry routing.Entry) (bool, error)

	// Backend returns the reverse-proxy handler for entry.
	Backend func(entry routing.Entry) http.Handler
}

func (h *Handler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	entry, ok := h.Table.Lookup(r.Host)
	if !ok {
		http.NotFound(w, r)
		return
	}

	h.Seen.Touch(entry.Host)

	ready, err := h.Ready(r.Context(), entry)
	if err != nil {
		http.Error(w, "checking app readiness", http.StatusBadGateway)
		return
	}
	if !ready {
		if err := h.Waker.Wake(r.Context(), entry.Host); err != nil {
			http.Error(w, "waking app", http.StatusBadGateway)
			return
		}
	}

	h.Backend(entry).ServeHTTP(w, r)
}
