<?php

declare(strict_types=1);

namespace App\AppManager\Application\Message;

/**
 * Contrato entrante publicado por el BC Build (Go — sin clase PHP que
 * compartir, a diferencia de los eventos que cruzan BCs dentro de este mismo
 * monorepo PHP). Ver docs/podium-domain/event-catalog.md y asyncapi.yaml.
 */
final readonly class BuildSucceeded
{
    public function __construct(
        public string $serviceName,
        public string $projectId,
        public string $version,
        public string $image,
    ) {
    }
}
