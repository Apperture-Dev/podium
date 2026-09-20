<?php

declare(strict_types=1);

namespace App\Build\Domain\Event;

final readonly class BuildSucceeded
{
    /**
     * @param array<string, string> $deployEnvVars      Leídas de podium.yaml por el propio Job — hoy siempre vacío (ver build/model.md)
     * @param array<string, string> $databaseDeclaration
     */
    public function __construct(
        public string $serviceName,
        public string $projectId,
        public string $version,
        public string $image,
        public int $port,
        public array $deployEnvVars,
        public array $databaseDeclaration,
    ) {
    }
}
