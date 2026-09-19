<?php

declare(strict_types=1);

namespace App\Deploy\Domain\Event;

final readonly class DeployFailed
{
    public function __construct(
        public string $serviceName,
        public string $projectId,
        public string $version,
        public string $errorMessage,
        public ?int $retryCount,
    ) {
    }
}
