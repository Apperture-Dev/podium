<?php

declare(strict_types=1);

namespace App\AppManager\Application\Message;

/** Contrato entrante publicado por el BC Deploy (Go). Ver event-catalog.md. */
final readonly class DeployFailed
{
    public function __construct(
        public string $serviceName,
        public string $projectId,
        public string $errorMessage,
    ) {
    }
}
