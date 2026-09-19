// Package events holds the message shapes that cross the PHP/Go boundary
// over Redis Streams — the JSON field names below must match the public
// property names Symfony's ObjectNormalizer reads off the PHP classes
// exactly (services/php/src/Build/Domain/Event/BuildJobRequested.php,
// services/php/src/Build/Application/Message/{JobSucceeded,JobFailed}.php).
package events

// BuildJobRequestedType is the FQCN Symfony puts in the envelope's
// headers.type for this message.
const BuildJobRequestedType = `App\Build\Domain\Event\BuildJobRequested`

// JobSucceededType is the FQCN this launcher must use when producing a
// success result, so Build\Application\EventHandler\JobSucceededHandler
// can route it.
const JobSucceededType = `App\Build\Application\Message\JobSucceeded`

// JobFailedType is the FQCN for a failure result.
const JobFailedType = `App\Build\Application\Message\JobFailed`

// BuildJobRequested is Build's outbound signal that a Job should be
// created — Command/EnvVars is always empty today per Template's MVP
// convention (the jobImage uses its own entrypoint).
type BuildJobRequested struct {
	BuildJobID string            `json:"buildJobId"`
	JobImage   string            `json:"jobImage"`
	Command    []string          `json:"command"`
	EnvVars    map[string]string `json:"envVars"`
}

// JobSucceeded reports a finished, successful Job back to Build.
// BuildEnvVars/DeployEnvVars/DatabaseDeclaration travel empty in this first
// version — see the plan's note on why (nothing populates them yet).
type JobSucceeded struct {
	BuildJobID          string            `json:"buildJobId"`
	Image               string            `json:"image"`
	BuildEnvVars        map[string]string `json:"buildEnvVars"`
	DeployEnvVars       map[string]string `json:"deployEnvVars"`
	DatabaseDeclaration map[string]any    `json:"databaseDeclaration"`
}

// JobFailed reports a finished, failed Job back to Build.
type JobFailed struct {
	BuildJobID   string `json:"buildJobId"`
	ErrorMessage string `json:"errorMessage"`
}
