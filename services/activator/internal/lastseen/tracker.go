// Package lastseen tracks, per tenant host, the timestamp of the most
// recent request the activator saw — the signal the sweep loop uses to
// decide when a tenant has gone idle long enough to scale to 0.
package lastseen

import (
	"sync"
	"time"
)

// Tracker is a concurrency-safe host -> last-seen-timestamp map.
type Tracker struct {
	mu   sync.Mutex
	seen map[string]time.Time
	now  func() time.Time
}

// NewTracker returns a Tracker using the real wall clock.
func NewTracker() *Tracker {
	return NewTrackerWithClock(time.Now)
}

// NewTrackerWithClock returns a Tracker using clock instead of time.Now —
// for deterministic tests.
func NewTrackerWithClock(clock func() time.Time) *Tracker {
	return &Tracker{seen: make(map[string]time.Time), now: clock}
}

// Touch records host as seen right now.
func (t *Tracker) Touch(host string) {
	t.mu.Lock()
	defer t.mu.Unlock()
	t.seen[host] = t.now()
}

// Idle reports whether host has gone unseen for at least threshold — a
// host that was never touched counts as idle.
func (t *Tracker) Idle(host string, threshold time.Duration) bool {
	t.mu.Lock()
	defer t.mu.Unlock()
	last, ok := t.seen[host]
	if !ok {
		return true
	}
	return t.now().Sub(last) >= threshold
}
