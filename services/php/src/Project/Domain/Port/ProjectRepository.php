<?php

declare(strict_types=1);

namespace App\Project\Domain\Port;

use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\ProjectId;

interface ProjectRepository
{
    public function get(ProjectId $id): Project;

    public function save(Project $project): void;
}
