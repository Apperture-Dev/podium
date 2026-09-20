// Package events holds the message shapes that cross the PHP/Go boundary
// over Redis Streams — the JSON field names below must match the public
// property names Symfony's ObjectNormalizer reads off the PHP classes
// exactly (services/php/src/Deploy/Domain/Event/DeployAttemptRequested.php,
// services/php/src/Deploy/Application/Message/{HealthCheckSucceeded,HealthCheckExhausted}.php).
package events

import (
	"bytes"
	"encoding/json"
)

// DeployAttemptRequestedType is the FQCN Symfony puts in the envelope's
// headers.type for this message.
const DeployAttemptRequestedType = `App\Deploy\Domain\Event\DeployAttemptRequested`

// HealthCheckSucceededType is the FQCN this launcher must use when
// producing a success result, so
// Deploy\Application\EventHandler\HealthCheckSucceededHandler can route it.
const HealthCheckSucceededType = `App\Deploy\Application\Message\HealthCheckSucceeded`

// HealthCheckExhaustedType is the FQCN for a failure result.
const HealthCheckExhaustedType = `App\Deploy\Application\Message\HealthCheckExhausted`

// DeployAttemptRequested is Deploy's outbound signal that a tenant
// Application should be created/updated. EnvVars/DatabaseDeclaration use
// FlexibleMap, not a plain map[string]any: PHP encodes an empty associative
// array as a JSON array ([]), not an object ({}), and encoding/json refuses
// to unmarshal a JSON array into ANY map type (map[string]any included —
// the mismatch is array-vs-object, not about the map's value type).
// Confirmed against a real captured envelope (see internal/messenger
// tests) where both fields were empty and serialized as [].
type DeployAttemptRequested struct {
	DeployAttemptID     string      `json:"deployAttemptId"`
	Hash                string      `json:"hash"`
	ServiceName         string      `json:"serviceName"`
	Image               string      `json:"image"`
	Port                int64       `json:"port"`
	EnvVars             FlexibleMap `json:"envVars"`
	DatabaseDeclaration FlexibleMap `json:"databaseDeclaration"`
	Database            DatabaseDeclaration `json:"database"`
}

// DatabaseDeclaration is the tenant's database need, already resolved by the
// Deploy BC: which of the two shapes the team used in its podium.yaml, and
// under which environment variable names it wants each value. No rule is
// applied here — "URL wins over the loose fields" is domain, and lives in PHP.
//
// Mode is "none", "url" or "parts"; an absent block unmarshals to the zero
// value, which Values() reports as "none".
type DatabaseDeclaration struct {
	Mode   string            `json:"mode"`
	UrlVar string            `json:"urlVar"`
	Vars   map[string]string `json:"vars"`
}

// Values renders the declaration as the chart's `database` values. The map is
// map[string]any all the way down because unstructured.SetNestedMap only
// accepts JSON types — a map[string]string panics when building the
// Application.
func (d DatabaseDeclaration) Values() map[string]any {
	mode := d.Mode
	if mode == "" {
		mode = "none"
	}

	vars := map[string]any{}
	for key, envVar := range d.Vars {
		vars[key] = envVar
	}

	return map[string]any{
		"mode":   mode,
		"urlVar": d.UrlVar,
		"vars":   vars,
	}
}

// FlexibleMap unmarshals a JSON object normally, and treats a JSON array
// (PHP's encoding of an empty associative array) or null as an empty map.
type FlexibleMap map[string]any

func (m *FlexibleMap) UnmarshalJSON(data []byte) error {
	trimmed := bytes.TrimSpace(data)
	if string(trimmed) == "null" || (len(trimmed) > 0 && trimmed[0] == '[') {
		*m = FlexibleMap{}
		return nil
	}
	var asMap map[string]any
	if err := json.Unmarshal(data, &asMap); err != nil {
		return err
	}
	*m = asMap
	return nil
}

// HealthCheckSucceeded reports a healthy Application back to Deploy.
type HealthCheckSucceeded struct {
	DeployAttemptID string `json:"deployAttemptId"`
}

// HealthCheckExhausted reports that health checks never went healthy
// within the launcher's own retry budget — that budget is infrastructure
// policy (ArgoCD/this launcher), never domain, per deploy/model.md.
// RetryCount is a pointer because it's optional (?int in PHP).
type HealthCheckExhausted struct {
	DeployAttemptID string `json:"deployAttemptId"`
	ErrorMessage    string `json:"errorMessage"`
	RetryCount      *int   `json:"retryCount"`
}
