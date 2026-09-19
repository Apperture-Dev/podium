package wake_test

import (
	"context"
	"errors"
	"sync"
	"sync/atomic"
	"testing"
	"time"

	"github.com/Apperture-Dev/podium/services/activator/internal/wake"
)

func TestWakeCoalescesConcurrentCallsForSameHost(t *testing.T) {
	var calls int32
	w := wake.New(func(ctx context.Context, host string) error {
		atomic.AddInt32(&calls, 1)
		time.Sleep(50 * time.Millisecond)
		return nil
	})

	var wg sync.WaitGroup
	for i := 0; i < 10; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			if err := w.Wake(context.Background(), "team-a.apperture.dev"); err != nil {
				t.Errorf("unexpected error: %v", err)
			}
		}()
	}
	wg.Wait()

	if got := atomic.LoadInt32(&calls); got != 1 {
		t.Fatalf("expected exactly 1 underlying scale call for 10 concurrent wakes of the same host, got %d", got)
	}
}

func TestWakeScalesDifferentHostsIndependently(t *testing.T) {
	seen := make(map[string]int)
	var mu sync.Mutex
	w := wake.New(func(ctx context.Context, host string) error {
		mu.Lock()
		seen[host]++
		mu.Unlock()
		return nil
	})

	var wg sync.WaitGroup
	for _, host := range []string{"team-a.apperture.dev", "team-b.apperture.dev"} {
		wg.Add(1)
		go func(h string) {
			defer wg.Done()
			_ = w.Wake(context.Background(), h)
		}(host)
	}
	wg.Wait()

	if seen["team-a.apperture.dev"] != 1 || seen["team-b.apperture.dev"] != 1 {
		t.Fatalf("expected exactly 1 call per distinct host, got %v", seen)
	}
}

func TestWakePropagatesError(t *testing.T) {
	boom := errors.New("scale failed")
	w := wake.New(func(ctx context.Context, host string) error {
		return boom
	})

	if err := w.Wake(context.Background(), "team-a.apperture.dev"); !errors.Is(err, boom) {
		t.Fatalf("expected wrapped/equal error %v, got %v", boom, err)
	}
}

func TestWakeAllowsRetryAfterPreviousCallCompletes(t *testing.T) {
	var calls int32
	w := wake.New(func(ctx context.Context, host string) error {
		atomic.AddInt32(&calls, 1)
		return nil
	})

	if err := w.Wake(context.Background(), "team-a.apperture.dev"); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if err := w.Wake(context.Background(), "team-a.apperture.dev"); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if got := atomic.LoadInt32(&calls); got != 2 {
		t.Fatalf("expected 2 sequential calls (not coalesced, previous one already finished), got %d", got)
	}
}
