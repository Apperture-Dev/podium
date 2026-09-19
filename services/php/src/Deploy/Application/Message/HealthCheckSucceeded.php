<?php

declare(strict_types=1);

namespace App\Deploy\Application\Message;

/**
 * Contrato entrante del futuro lanzador de ArgoCD (Go — sin clase PHP que
 * compartir, diferido, sin productor real todavía). Ver
 * docs/podium-domain/event-catalog.md y asyncapi.yaml.
 */
final readonly class HealthCheckSucceeded
{
    public function __construct(
        public string $deployAttemptId,
    ) {
    }
}
