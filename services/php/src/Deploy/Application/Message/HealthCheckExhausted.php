<?php

declare(strict_types=1);

namespace App\Deploy\Application\Message;

/**
 * Contrato entrante del futuro lanzador de ArgoCD (Go, diferido, sin
 * productor real todavía). `retryCount` es informativo — la política de
 * reintentos la decide ArgoCD/el lanzador, no este dominio. Ver
 * event-catalog.md.
 */
final readonly class HealthCheckExhausted
{
    public function __construct(
        public string $deployAttemptId,
        public string $errorMessage,
        public ?int $retryCount = null,
    ) {
    }
}
