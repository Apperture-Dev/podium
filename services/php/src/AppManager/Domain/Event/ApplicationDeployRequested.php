<?php

declare(strict_types=1);

namespace App\AppManager\Domain\Event;

final readonly class ApplicationDeployRequested
{
    /**
     * Lleva de dónde salió la imagen (`commitId`/`repositoryUrl`/`provider`)
     * en lugar de las env vars y la base de datos: así Deploy lee del
     * `podium.yaml` de esa misma revisión lo que necesita, sin que App Manager
     * ni Build transporten datos que no son suyos.
     */
    public function __construct(
        public string $serviceName,
        public string $projectId,
        public string $version,
        public string $image,
        public int $port,
        public string $commitId,
        public string $repositoryUrl,
        public string $provider,
    ) {
    }
}
