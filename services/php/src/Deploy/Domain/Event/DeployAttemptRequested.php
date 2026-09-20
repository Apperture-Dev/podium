<?php

declare(strict_types=1);

namespace App\Deploy\Domain\Event;

final readonly class DeployAttemptRequested
{
    /**
     * @param array<string, string> $envVars
     * @param array<string, string> $databaseDeclaration
     */
    public function __construct(
        public string $deployAttemptId,
        public string $hash,
        public string $serviceName,
        public string $image,
        public int $port,
        public array $envVars,
        public array $databaseDeclaration,
    ) {
    }
}
