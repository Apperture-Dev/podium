<?php

declare(strict_types=1);

namespace App\AppManager\Domain\Event;

final readonly class ApplicationRegistered
{
    public function __construct(
        public string $applicationId,
        public string $serviceName,
        public string $projectId,
        public string $teamId,
    ) {
    }
}
