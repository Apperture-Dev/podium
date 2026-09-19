// Package routing holds the host -> tenant destination table the activator
// builds from watching labeled Ingresses/Deployments/Services, independent
// of how that table gets populated (informers) or consumed (the proxy).
package routing

import "sync"

// Entry is one tenant's routing destination: where to proxy a request once
// its app is awake, and which Deployment to scale to wake/sleep it.
type Entry struct {
	Host           string
	Namespace      string
	DeploymentName string
	ServiceName    string
	ServicePort    int32
	IngressName    string
}

// Table is a concurrency-safe host -> Entry lookup.
type Table struct {
	mu      sync.RWMutex
	entries map[string]Entry
}

func NewTable() *Table {
	return &Table{entries: make(map[string]Entry)}
}

// Set inserts or replaces the entry for e.Host.
func (t *Table) Set(e Entry) {
	t.mu.Lock()
	defer t.mu.Unlock()
	t.entries[e.Host] = e
}

// Delete removes host, if present.
func (t *Table) Delete(host string) {
	t.mu.Lock()
	defer t.mu.Unlock()
	delete(t.entries, host)
}

// Lookup returns the entry for host, and whether it was found.
func (t *Table) Lookup(host string) (Entry, bool) {
	t.mu.RLock()
	defer t.mu.RUnlock()
	e, ok := t.entries[host]
	return e, ok
}

// Hosts returns every host currently known, in no particular order — used
// by the sweep loop to iterate candidates for scale-to-0.
func (t *Table) Hosts() []string {
	t.mu.RLock()
	defer t.mu.RUnlock()
	hosts := make([]string, 0, len(t.entries))
	for h := range t.entries {
		hosts = append(hosts, h)
	}
	return hosts
}
