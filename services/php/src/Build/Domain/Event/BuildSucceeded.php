<?php

declare(strict_types=1);

namespace App\Build\Domain\Event;

final readonly class BuildSucceeded
{
    /**
     * `commitId`/`repositoryUrl`/`provider` viajan porque Deploy necesita leer
     * el `podium.yaml` de esa misma revisión. No son datos de despliegue que
     * Build transporte por cuenta ajena: son de dónde salió esta imagen, que
     * es precisamente lo que Build sabe.
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
