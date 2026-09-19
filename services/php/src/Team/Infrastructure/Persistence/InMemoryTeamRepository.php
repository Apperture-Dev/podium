<?php

declare(strict_types=1);

namespace App\Team\Infrastructure\Persistence;

use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\Team;
use App\Team\Domain\ValueObject\TeamId;
use RuntimeException;

/**
 * Persistencia real (Doctrine/Postgres u otra) sin decidir todavía — placeholder
 * para poder correr el walking skeleton hoy. No usar en producción: no persiste
 * entre procesos. Mismo patrón que App\Project\Infrastructure\Persistence\InMemoryProjectRepository.
 */
final class InMemoryTeamRepository implements TeamRepository
{
    /** @var array<string, Team> */
    private array $teams = [];

    public function get(TeamId $id): Team
    {
        return $this->teams[$id->toString()]
            ?? throw new RuntimeException(\sprintf('Team "%s" not found.', $id->toString()));
    }

    public function save(Team $team): void
    {
        $this->teams[$team->id()->toString()] = $team;
    }
}
