<?php

declare(strict_types=1);

namespace App\Project\Domain\Event;

final readonly class ProjectRegistered
{
    public function __construct(
        public string $projectId,
        public string $repositoryUrl,
        public string $teamId,
    ) {
    }
}
