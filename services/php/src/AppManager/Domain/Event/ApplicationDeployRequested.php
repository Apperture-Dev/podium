<?php

declare(strict_types=1);

namespace App\AppManager\Domain\Event;

final readonly class ApplicationDeployRequested
{
    public function __construct(
        public string $serviceName,
        public string $projectId,
        public string $version,
        public string $image,
    ) {
    }
}
