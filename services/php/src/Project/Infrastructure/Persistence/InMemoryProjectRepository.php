<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Persistence;

use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\ProjectId;
use RuntimeException;

/**
 * Persistencia real (Doctrine/Postgres u otra) sin decidir todavía — placeholder
 * para poder correr el walking skeleton hoy. No usar en producción: no persiste
 * entre procesos.
 */
final class InMemoryProjectRepository implements ProjectRepository
{
    /** @var array<string, Project> */
    private array $projects = [];

    public function get(ProjectId $id): Project
    {
        return $this->projects[$id->toString()]
            ?? throw new RuntimeException(\sprintf('Project "%s" not found.', $id->toString()));
    }

    public function save(Project $project): void
    {
        $this->projects[$project->id()->toString()] = $project;
    }
}
