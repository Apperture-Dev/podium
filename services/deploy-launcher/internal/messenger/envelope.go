// Package messenger encodes/decodes the wire format Symfony Messenger's
// Redis transport uses once configured with
// messenger.transport.symfony_serializer and phpredis's own serializer
// disabled (serializer=0 on the DSN) — plain JSON, not PHP's native
// serialize() format.
//
// This is an intentional copy of services/build-launcher/internal/messenger
// (byte-for-byte identical logic) — there is no shared Go module between
// services in this repo, and it isn't worth introducing one (go.work or an
// internal module) for ~40 lines. If this envelope logic ever changes, it
// has to change in both places; keep them in sync by hand.
package messenger

import (
	"encoding/json"
	"fmt"
)

type envelopeWire struct {
	Body    string            `json:"body"`
	Headers map[string]string `json:"headers"`
}

// Decode parses a raw Redis Stream "message" field value into the
// message's PHP fully-qualified class name (from headers.type) and its
// JSON body (still encoded, ready for json.Unmarshal into the matching
// events.* struct).
func Decode(raw []byte) (msgType string, body []byte, err error) {
	var w envelopeWire
	if err := json.Unmarshal(raw, &w); err != nil {
		return "", nil, fmt.Errorf("decode envelope: %w", err)
	}
	return w.Headers["type"], []byte(w.Body), nil
}

// Encode builds the same wire shape Symfony's Serializer produces, so PHP
// can read it back: {"body": "<json>", "headers": {"type": fqcn,
// "Content-Type": "application/json"}}.
func Encode(fqcn string, body []byte) ([]byte, error) {
	w := envelopeWire{
		Body: string(body),
		Headers: map[string]string{
			"type":         fqcn,
			"Content-Type": "application/json",
		},
	}
	out, err := json.Marshal(w)
	if err != nil {
		return nil, fmt.Errorf("encode envelope: %w", err)
	}
	return out, nil
}
