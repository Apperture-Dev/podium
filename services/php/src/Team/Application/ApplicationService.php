<?php

declare(strict_types=1);

namespace App\Team\Application;

use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\Team;
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
}
