package routing_test

import (
	"sync"
	"testing"

	"github.com/Apperture-Dev/podium/services/activator/internal/routing"
)

func TestTableLookupMissingHostReturnsFalse(t *testing.T) {
	tbl := routing.NewTable()

	_, ok := tbl.Lookup("missing.apperture.dev")
	if ok {
		t.Fatal("expected ok=false for a host that was never set")
	}
}

func TestTableSetThenLookupReturnsEntry(t *testing.T) {
	tbl := routing.NewTable()
	entry := routing.Entry{
		Host:           "team-a.apperture.dev",
		Namespace:      "team-a",
		DeploymentName: "app",
		ServiceName:    "app",
		ServicePort:    8080,
	}

	tbl.Set(entry)

	got, ok := tbl.Lookup("team-a.apperture.dev")
	if !ok {
		t.Fatal("expected ok=true after Set")
	}
	if got != entry {
		t.Fatalf("got %+v, want %+v", got, entry)
	}
}

func TestTableDeleteRemovesEntry(t *testing.T) {
	tbl := routing.NewTable()
	tbl.Set(routing.Entry{Host: "team-a.apperture.dev", Namespace: "team-a"})

	tbl.Delete("team-a.apperture.dev")

	if _, ok := tbl.Lookup("team-a.apperture.dev"); ok {
		t.Fatal("expected entry to be gone after Delete")
	}
}

func TestTableHostsListsAllKnownHosts(t *testing.T) {
	tbl := routing.NewTable()
	tbl.Set(routing.Entry{Host: "team-a.apperture.dev"})
	tbl.Set(routing.Entry{Host: "team-b.apperture.dev"})

	hosts := tbl.Hosts()

	if len(hosts) != 2 {
		t.Fatalf("expected 2 hosts, got %d: %v", len(hosts), hosts)
	}
}

func TestTableConcurrentAccessDoesNotRace(t *testing.T) {
	tbl := routing.NewTable()
	var wg sync.WaitGroup

	for i := 0; i < 50; i++ {
		wg.Add(2)
		go func(i int) {
			defer wg.Done()
			tbl.Set(routing.Entry{Host: "team.apperture.dev"})
		}(i)
		go func() {
			defer wg.Done()
			tbl.Lookup("team.apperture.dev")
			tbl.Hosts()
		}()
	}

	wg.Wait()
}
