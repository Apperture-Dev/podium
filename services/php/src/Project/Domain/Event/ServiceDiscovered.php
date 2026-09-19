<?php

declare(strict_types=1);

namespace App\Project\Domain\Event;

final readonly class ServiceDiscovered
{
    public function __construct(
        public string $serviceName,
        public string $projectId,
        public string $lang,
        public string $framework,
    ) {
    }
}
