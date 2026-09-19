// Package messenger encodes/decodes the wire format Symfony Messenger's
// Redis transport uses once configured with
// messenger.transport.symfony_serializer and phpredis's own serializer
// disabled (serializer=0 on the DSN) — plain JSON, not PHP's native
// serialize() format. See the build-launcher plan's "hallazgo crítico"
// section for why both of those config changes were needed on the PHP side
// before this package could exist.
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
