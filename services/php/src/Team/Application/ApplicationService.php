<?php

declare(strict_types=1);

namespace App\Team\Application;

use App\Shared\Domain\Exception\AccessDeniedException;
use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\Team;
use App\Team\Domain\ValueObject\TeamDTO;
use App\Team\Domain\ValueObject\TeamId;
use App\Team\Domain\ValueObject\TeamName;
use App\Team\Domain\ValueObject\UserId;

final readonly class ApplicationService
{
    public function __construct(
        private TeamRepository $teams,
    ) {
    }

    public function registerTeam(string $name, string $creatorUserId): string
    {
        $team = Team::register(
            TeamName::fromString($name),
            UserId::fromString($creatorUserId),
        );

        $this->teams->save($team);

        return $team->id()->toString();
    }

    /** @return list<TeamDTO> */
    public function listTeamsForUser(string $userId): array
    {
        $teams = $this->teams->findByUserId(UserId::fromString($userId));

        return array_map(static fn (Team $team): TeamDTO => $team->toDTO(), $teams);
    }

    /** Antes valida que el userId autenticado sea miembro de ese Team. */
    public function getTeam(string $teamId, string $requestingUserId): TeamDTO
    {
        $team = $this->teams->get(TeamId::fromString($teamId));

        if (!$team->hasMember(UserId::fromString($requestingUserId))) {
            throw new AccessDeniedException(\sprintf('User "%s" cannot access team "%s".', $requestingUserId, $teamId));
        }

        return $team->toDTO();
    }
}
