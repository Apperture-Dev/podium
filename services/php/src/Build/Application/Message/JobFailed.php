<?php

declare(strict_types=1);

namespace App\Build\Application\Message;

/** Contrato entrante del futuro lanzador de Kubernetes (Go, diferido, sin productor real todavía). Ver event-catalog.md. */
final readonly class JobFailed
{
    public function __construct(
        public string $buildJobId,
        public string $errorMessage,
    ) {
    }
}
