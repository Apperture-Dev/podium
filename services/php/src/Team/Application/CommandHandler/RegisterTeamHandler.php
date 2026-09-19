<?php

declare(strict_types=1);

namespace App\Team\Application\CommandHandler;

use App\Team\Application\Command\RegisterTeam;
use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\Team;
use App\Team\Domain\ValueObject\TeamName;
use App\Team\Domain\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RegisterTeamHandler
{
    public function __construct(
        private TeamRepository $teams,
    ) {
    }

    public function __invoke(RegisterTeam $command): string
    {
        $team = Team::register(
            TeamName::fromString($command->name),
            UserId::fromString($command->creatorUserId),
        );

        $this->teams->save($team);

        return $team->id()->toString();
    }
}
