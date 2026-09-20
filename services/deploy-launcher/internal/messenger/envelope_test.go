package messenger_test

import (
	"encoding/json"
	"testing"

	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/events"
	"github.com/Apperture-Dev/podium/services/deploy-launcher/internal/messenger"
)

// realFixture is a byte-for-byte capture of the "message" field XADD'd by
// the real PHP app (services/php) after switching deploy_attempt_requested
// to messenger.transport.symfony_serializer with phpredis's SERIALIZER_NONE
// — captured during Fase 0 of the deploy-launcher plan, not hand-written.
// envVars/databaseDeclaration are both empty here, and PHP serialized them
// as JSON arrays ([]), not objects ({}) — this is the case
// events.DeployAttemptRequested's map[string]any fields exist to survive.
const realFixture = `{"body":"{\"deployAttemptId\":\"diagnostic-deploy-attempt-id\",\"hash\":\"abc12345\",\"serviceName\":\"diagnostic-service\",\"image\":\"registry.apperture.dev\\/diagnostic-project-diagnostic-service:v1\",\"envVars\":[],\"databaseDeclaration\":[]}","headers":{"type":"App\\Deploy\\Domain\\Event\\DeployAttemptRequested","X-Message-Stamp-Symfony\\Component\\Messenger\\Stamp\\BusNameStamp":"[{\"busName\":\"messenger.bus.default\"}]","Content-Type":"application\/json"}}`

func TestDecodeExtractsTypeFromRealPHPFixture(t *testing.T) {
	msgType, _, err := messenger.Decode([]byte(realFixture))
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if msgType != events.DeployAttemptRequestedType {
		t.Fatalf("got type %q, want %q", msgType, events.DeployAttemptRequestedType)
	}
}

func TestDecodeExtractsBodyFromRealPHPFixtureWithEmptyMapFields(t *testing.T) {
	_, body, err := messenger.Decode([]byte(realFixture))
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	var req events.DeployAttemptRequested
	if err := json.Unmarshal(body, &req); err != nil {
		t.Fatalf("body is not valid DeployAttemptRequested JSON: %v (body=%s)", err, body)
	}

	if req.DeployAttemptID != "diagnostic-deploy-attempt-id" {
		t.Fatalf("got deployAttemptId %q", req.DeployAttemptID)
	}
	if req.Hash != "abc12345" {
		t.Fatalf("got hash %q, want abc12345", req.Hash)
	}
	if req.ServiceName != "diagnostic-service" {
		t.Fatalf("got serviceName %q, want diagnostic-service", req.ServiceName)
	}
	if req.Image != "registry.apperture.dev/diagnostic-project-diagnostic-service:v1" {
		t.Fatalf("got image %q", req.Image)
	}
	if len(req.EnvVars) != 0 {
		t.Fatalf("expected empty envVars (decoded from a JSON array), got %v", req.EnvVars)
	}
	// El envelope capturado es anterior a que Deploy resolviera la base de
	// datos, así que no trae el campo: tiene que decodificarse como "sin base
	// de datos" y no reventar.
	if req.Database.Values()["mode"] != "none" {
		t.Fatalf("expected mode none for an envelope with no database block, got %v", req.Database.Values()["mode"])
	}
}

func TestDecodeRejectsInvalidEnvelope(t *testing.T) {
	_, _, err := messenger.Decode([]byte("not json"))
	if err == nil {
		t.Fatal("expected an error for invalid JSON")
	}
}

func TestEncodeThenDecodeRoundTrips(t *testing.T) {
	original := events.HealthCheckSucceeded{DeployAttemptID: "abc123"}
	bodyJSON, err := json.Marshal(original)
	if err != nil {
		t.Fatalf("unexpected error marshalling fixture: %v", err)
	}

	encoded, err := messenger.Encode(events.HealthCheckSucceededType, bodyJSON)
	if err != nil {
		t.Fatalf("unexpected error encoding: %v", err)
	}

	gotType, gotBody, err := messenger.Decode(encoded)
	if err != nil {
		t.Fatalf("unexpected error decoding round-tripped envelope: %v", err)
	}
	if gotType != events.HealthCheckSucceededType {
		t.Fatalf("got type %q, want %q", gotType, events.HealthCheckSucceededType)
	}

	var roundTripped events.HealthCheckSucceeded
	if err := json.Unmarshal(gotBody, &roundTripped); err != nil {
		t.Fatalf("round-tripped body is not valid JSON: %v", err)
	}
	if roundTripped != original {
		t.Fatalf("got %+v, want %+v", roundTripped, original)
	}
}
