package events_test

import (
	"encoding/json"
	"testing"

	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/events"
)

func TestFlexibleMapUnmarshalsAnEmptyJSONArrayAsEmptyMap(t *testing.T) {
	var m events.FlexibleMap
	if err := json.Unmarshal([]byte(`[]`), &m); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if len(m) != 0 {
		t.Fatalf("expected an empty map, got %v", m)
	}
}

func TestFlexibleMapUnmarshalsNullAsEmptyMap(t *testing.T) {
	var m events.FlexibleMap
	if err := json.Unmarshal([]byte(`null`), &m); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if len(m) != 0 {
		t.Fatalf("expected an empty map, got %v", m)
	}
}

func TestFlexibleMapUnmarshalsAJSONObjectNormally(t *testing.T) {
	var m events.FlexibleMap
	if err := json.Unmarshal([]byte(`{"API_URL":"https://x"}`), &m); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if m["API_URL"] != "https://x" {
		t.Fatalf("got %v", m)
	}
}

func TestFlexibleMapRejectsANonEmptyArray(t *testing.T) {
	var m events.FlexibleMap
	err := json.Unmarshal([]byte(`["not", "a", "map"]`), &m)
	if err != nil {
		t.Fatalf("unexpected error (non-empty arrays should still decode as empty, matching PHP's own ambiguity): %v", err)
	}
	if len(m) != 0 {
		t.Fatalf("expected a non-empty PHP-style array to still decode as an empty map, got %v", m)
	}
}
