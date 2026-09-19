// Package killswitch holds the on/off flag that gates whether tenant
// traffic routes through the activator at all, and the reconciler that
// applies that flag by patching each tenant's Ingress backend. The same
// mechanism serves two purposes: the cutover (turning routing through the
// activator on, only after the hackathon's 22:30 feature freeze) and the
// kill switch (turning it back off instantly if it misbehaves) are the same
// operation in opposite directions.
package killswitch

import "sync"

// Flag is a concurrency-safe on/off toggle, driven by watching a ConfigMap
// (see cmd/activator) — starts disabled so a freshly deployed activator is
// inert until explicitly cut over.
type Flag struct {
	mu      sync.RWMutex
	enabled bool
}

func NewFlag() *Flag {
	return &Flag{}
}

func (f *Flag) Enabled() bool {
	f.mu.RLock()
	defer f.mu.RUnlock()
	return f.enabled
}

func (f *Flag) Set(enabled bool) {
	f.mu.Lock()
	defer f.mu.Unlock()
	f.enabled = enabled
}
