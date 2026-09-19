package killswitch_test

import (
	"testing"

	"github.com/Apperture-Dev/podium/services/activator/internal/killswitch"
)

func TestFlagDefaultsToDisabled(t *testing.T) {
	f := killswitch.NewFlag()

	if f.Enabled() {
		t.Fatal("expected a new Flag to start disabled (inert by default)")
	}
}

func TestFlagSetChangesEnabled(t *testing.T) {
	f := killswitch.NewFlag()

	f.Set(true)
	if !f.Enabled() {
		t.Fatal("expected Enabled() to be true after Set(true)")
	}

	f.Set(false)
	if f.Enabled() {
		t.Fatal("expected Enabled() to be false after Set(false)")
	}
}
