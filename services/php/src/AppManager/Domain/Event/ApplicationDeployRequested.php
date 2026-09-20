<?php

declare(strict_types=1);

namespace App\AppManager\Domain\Event;

final readonly class ApplicationDeployRequested
{
    /**
     * @param array<string, string> $deployEnvVars
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
