// Package wake coalesces concurrent wake-up requests for the same cold
// tenant host into a single underlying scale operation.
package wake

import (
	"context"

	"golang.org/x/sync/singleflight"
)

// ScaleFunc scales the Deployment behind host to at least one replica and
// blocks until it is ready (or returns an error).
type ScaleFunc func(ctx context.Context, host string) error

// Waker coalesces concurrent Wake calls for the same host via singleflight,
// so N simultaneous requests to a cold app trigger exactly one scale
// operation.
type Waker struct {
	group singleflight.Group
	scale ScaleFunc
}

func New(scale ScaleFunc) *Waker {
	return &Waker{scale: scale}
}

// Wake scales host if needed. Concurrent callers for the same host share
// the result of a single in-flight call; once that call finishes, the next
// Wake starts a fresh one.
func (w *Waker) Wake(ctx context.Context, host string) error {
	_, err, _ := w.group.Do(host, func() (any, error) {
		return nil, w.scale(ctx, host)
	})
	return err
}
