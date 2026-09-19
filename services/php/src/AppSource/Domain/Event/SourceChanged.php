<?php

declare(strict_types=1);

namespace App\AppSource\Domain\Event;

final readonly class SourceChanged
{
    public function __construct(
        public string $projectId,
        public string $revision,
        public string $repositoryUrl,
        public string $provider,
    ) {
    }
}
