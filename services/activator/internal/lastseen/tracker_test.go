package lastseen_test

import (
	"testing"
	"time"

	"github.com/Apperture-Dev/podium/services/activator/internal/lastseen"
)

func TestIdleIsTrueForAHostNeverTouched(t *testing.T) {
	tr := lastseen.NewTracker()

	if !tr.Idle("team-a.apperture.dev", time.Minute) {
		t.Fatal("expected a never-seen host to count as idle")
	}
}

func TestIdleIsFalseRightAfterTouch(t *testing.T) {
	tr := lastseen.NewTracker()

	tr.Touch("team-a.apperture.dev")

	if tr.Idle("team-a.apperture.dev", time.Minute) {
		t.Fatal("expected a just-touched host to not be idle yet")
	}
}

func TestIdleBecomesTrueOnceThresholdElapses(t *testing.T) {
	now := time.Now()
	clock := func() time.Time { return now }
	tr := lastseen.NewTrackerWithClock(clock)

	tr.Touch("team-a.apperture.dev")
	now = now.Add(2 * time.Minute)

	if !tr.Idle("team-a.apperture.dev", time.Minute) {
		t.Fatal("expected host to be idle once the threshold has elapsed since the last touch")
	}
}

func TestTouchResetsTheIdleClock(t *testing.T) {
	now := time.Now()
	clock := func() time.Time { return now }
	tr := lastseen.NewTrackerWithClock(clock)

	tr.Touch("team-a.apperture.dev")
	now = now.Add(2 * time.Minute)
	tr.Touch("team-a.apperture.dev")

	if tr.Idle("team-a.apperture.dev", time.Minute) {
		t.Fatal("expected the second Touch to reset idleness")
	}
}
