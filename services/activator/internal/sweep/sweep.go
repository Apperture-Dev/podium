// Package sweep periodically scales idle tenants down to 0 replicas — the
// counterpart to wake.Waker: without this, the "wake" side never gets
// exercised because nothing ever goes back to sleep.
package sweep

import (
	"context"
	"errors"
	"fmt"
	"time"

	"github.com/Apperture-Dev/podium/services/activator/internal/lastseen"
	"github.com/Apperture-Dev/podium/services/activator/internal/routing"
	"github.com/Apperture-Dev/podium/services/activator/internal/scale"
)

// Sweeper scales every tenant that has been idle for at least Threshold
// down to 0 replicas.
type Sweeper struct {
	Table     *routing.Table
	Seen      *lastseen.Tracker
	Patcher   *scale.Patcher
	Threshold time.Duration
}

// Run performs one sweep pass. It keeps going past a single tenant's
// failure (one broken Deployment shouldn't stop the rest of the fleet from
// scaling to 0) and returns every error it hit, joined.
func (s *Sweeper) Run(ctx context.Context) error {
	var errs []error
	for _, host := range s.Table.Hosts() {
		if !s.Seen.Idle(host, s.Threshold) {
			continue
		}
		entry, ok := s.Table.Lookup(host)
		if !ok {
			continue
		}
		if err := s.Patcher.SetReplicas(ctx, entry.Namespace, entry.DeploymentName, 0); err != nil {
			errs = append(errs, fmt.Errorf("%s: %w", host, err))
		}
	}
	return errors.Join(errs...)
}
