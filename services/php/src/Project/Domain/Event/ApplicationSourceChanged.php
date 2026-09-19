<?php

declare(strict_types=1);

namespace App\Project\Domain\Event;

final readonly class ApplicationSourceChanged
{
    public function __construct(
        public string $serviceName,
        public string $projectId,
        public string $revision,
        public string $repositoryUrl,
        public string $provider,
    ) {
    }
}
