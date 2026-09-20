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

// Regresión: un servicio sin base de datos llega con "vars": [], porque PHP
// serializa un array asociativo vacío como array JSON y no como objeto. Con
// un map[string]string pelado, encoding/json se niega a decodificarlo y el
// lanzador descarta el mensaje entero — que es justo lo que dejó un deploy
// colgado en "Desplegando" sin crear su Application.
func TestDeployAttemptRequestedDecodesEmptyVarsSerializedAsArray(t *testing.T) {
	payload := []byte(`{"deployAttemptId":"01J","hash":"9f04c56e","serviceName":"web","image":"reg/web:v1","port":80,"envVars":[],"database":{"mode":"none","urlVar":"","vars":[]}}`)

	var req events.DeployAttemptRequested
	if err := json.Unmarshal(payload, &req); err != nil {
		t.Fatalf("no debería fallar al decodificar vars vacío: %v", err)
	}

	if req.Database.Values()["mode"] != "none" {
		t.Fatalf("got mode %v, want none", req.Database.Values()["mode"])
	}
	if vars, ok := req.Database.Values()["vars"].(map[string]any); !ok || len(vars) != 0 {
		t.Fatalf("got vars %#v, want an empty map", req.Database.Values()["vars"])
	}
}

// El caso con datos tiene que seguir funcionando igual.
func TestDeployAttemptRequestedDecodesVarsWithValues(t *testing.T) {
	payload := []byte(`{"database":{"mode":"parts","urlVar":"","vars":{"dbname":"DB_NAME","username":"DB_USER"}}}`)

	var req events.DeployAttemptRequested
	if err := json.Unmarshal(payload, &req); err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	vars := req.Database.Values()["vars"].(map[string]any)
	if vars["dbname"] != "DB_NAME" || vars["username"] != "DB_USER" {
		t.Fatalf("got vars %#v", vars)
	}
}
