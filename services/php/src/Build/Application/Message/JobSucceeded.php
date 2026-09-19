<?php

declare(strict_types=1);

namespace App\Build\Application\Message;

/**
 * Contrato entrante del futuro lanzador de Kubernetes (Go — sin clase PHP
 * que compartir, diferido, sin productor real todavía). Ver
 * docs/podium-domain/event-catalog.md y asyncapi.yaml.
 */
final readonly class JobSucceeded
{
    /**
     * @param array<string, string> $buildEnvVars
     * @param array<string, string> $deployEnvVars
     * @param array<string, string> $databaseDeclaration
     */
    public function __construct(
        public string $buildJobId,
        public string $image,
        public array $buildEnvVars,
        public array $deployEnvVars,
        public array $databaseDeclaration,
    ) {
    }
}
