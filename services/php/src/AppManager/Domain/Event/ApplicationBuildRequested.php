<?php

declare(strict_types=1);

namespace App\AppManager\Domain\Event;

final readonly class ApplicationBuildRequested
{
    public function __construct(
        public string $serviceName,
        public string $projectId,
        public string $templateId,
        public string $version,
        public string $revision,
        public string $repositoryUrl,
        public string $provider,
    ) {
    }
}
