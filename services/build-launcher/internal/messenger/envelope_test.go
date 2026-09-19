package messenger_test

import (
	"encoding/json"
	"reflect"
	"testing"

	"github.com/Apperture-Dev/podium/services/build-launcher/internal/events"
	"github.com/Apperture-Dev/podium/services/build-launcher/internal/messenger"
)

// realFixture is a byte-for-byte capture of the "message" field XADD'd by
// the real PHP app (services/php) after switching build_job_requested to
// messenger.transport.symfony_serializer and disabling phpredis's own
// SERIALIZER_PHP wrapping (MESSENGER_TRANSPORT_DSN=...?serializer=0) — see
// the plan's "hallazgo crítico" section. Not a hand-written guess.
const realFixture = `{"body":"{\"buildJobId\":\"diagnostic-build-job-id\",\"jobImage\":\"services\\/build-runner:latest\",\"command\":[],\"envVars\":{\"SERVICE_NAME\":\"diagnostic\",\"PROJECT_ID\":\"diagnostic-project\"}}","headers":{"type":"App\\Build\\Domain\\Event\\BuildJobRequested","X-Message-Stamp-Symfony\\Component\\Messenger\\Stamp\\BusNameStamp":"[{\"busName\":\"messenger.bus.default\"}]","Content-Type":"application\/json"}}`

func TestDecodeExtractsTypeFromRealPHPFixture(t *testing.T) {
	msgType, _, err := messenger.Decode([]byte(realFixture))
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}
	if msgType != events.BuildJobRequestedType {
		t.Fatalf("got type %q, want %q", msgType, events.BuildJobRequestedType)
	}
}

func TestDecodeExtractsBodyFromRealPHPFixture(t *testing.T) {
	_, body, err := messenger.Decode([]byte(realFixture))
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	var req events.BuildJobRequested
	if err := json.Unmarshal(body, &req); err != nil {
		t.Fatalf("body is not valid BuildJobRequested JSON: %v (body=%s)", err, body)
	}

	if req.BuildJobID != "diagnostic-build-job-id" {
		t.Fatalf("got buildJobId %q, want diagnostic-build-job-id", req.BuildJobID)
	}
	if req.JobImage != "services/build-runner:latest" {
		t.Fatalf("got jobImage %q, want services/build-runner:latest", req.JobImage)
	}
	if len(req.Command) != 0 {
		t.Fatalf("expected empty command, got %v", req.Command)
	}
	if req.EnvVars["SERVICE_NAME"] != "diagnostic" || req.EnvVars["PROJECT_ID"] != "diagnostic-project" {
		t.Fatalf("unexpected envVars: %v", req.EnvVars)
	}
}

func TestDecodeRejectsInvalidEnvelope(t *testing.T) {
	_, _, err := messenger.Decode([]byte("not json"))
	if err == nil {
		t.Fatal("expected an error for invalid JSON")
	}
}

func TestEncodeThenDecodeRoundTrips(t *testing.T) {
	original := events.JobSucceeded{
		BuildJobID:          "abc123",
		Image:               "registry.apperture.dev/team-a-app:v1",
		BuildEnvVars:        map[string]string{},
		DeployEnvVars:       map[string]string{},
		DatabaseDeclaration: map[string]any{},
	}
	bodyJSON, err := json.Marshal(original)
	if err != nil {
		t.Fatalf("unexpected error marshalling fixture: %v", err)
	}

	encoded, err := messenger.Encode(events.JobSucceededType, bodyJSON)
	if err != nil {
		t.Fatalf("unexpected error encoding: %v", err)
	}

	gotType, gotBody, err := messenger.Decode(encoded)
	if err != nil {
		t.Fatalf("unexpected error decoding round-tripped envelope: %v", err)
	}
	if gotType != events.JobSucceededType {
		t.Fatalf("got type %q, want %q", gotType, events.JobSucceededType)
	}

	var roundTripped events.JobSucceeded
	if err := json.Unmarshal(gotBody, &roundTripped); err != nil {
		t.Fatalf("round-tripped body is not valid JSON: %v", err)
	}
	if !reflect.DeepEqual(roundTripped, original) {
		t.Fatalf("got %+v, want %+v", roundTripped, original)
	}
}

func TestEncodeProducesTheExactSymfonyEnvelopeShape(t *testing.T) {
	encoded, err := messenger.Encode(events.JobFailedType, []byte(`{"buildJobId":"x","errorMessage":"boom"}`))
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	var wire map[string]any
	if err := json.Unmarshal(encoded, &wire); err != nil {
		t.Fatalf("encoded output is not valid JSON: %v", err)
	}
	if _, ok := wire["body"].(string); !ok {
		t.Fatalf("expected top-level \"body\" to be a JSON string, got %+v", wire["body"])
	}
	headers, ok := wire["headers"].(map[string]any)
	if !ok {
		t.Fatalf("expected top-level \"headers\" object, got %+v", wire["headers"])
	}
	if headers["type"] != events.JobFailedType {
		t.Fatalf("got headers.type %v, want %q", headers["type"], events.JobFailedType)
	}
}
