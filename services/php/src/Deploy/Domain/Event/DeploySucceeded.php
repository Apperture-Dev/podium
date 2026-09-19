<?php

declare(strict_types=1);

namespace App\Deploy\Domain\Event;

final readonly class DeploySucceeded
{
    public function __construct(
        public string $serviceName,
        public string $projectId,
        public string $version,
    ) {
    }
}
