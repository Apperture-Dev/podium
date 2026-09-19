<?php

declare(strict_types=1);

namespace App\Build\Domain\Event;

final readonly class BuildFailed
{
    public function __construct(
        public string $serviceName,
        public string $projectId,
        public string $version,
        public string $errorMessage,
    ) {
    }
}
