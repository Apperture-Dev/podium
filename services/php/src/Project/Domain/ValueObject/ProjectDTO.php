<?php

declare(strict_types=1);

namespace App\Project\Domain\ValueObject;

final readonly class ProjectDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $hash,
        public string $repositoryUrl,
        public string $teamId,
    ) {
    }
}
