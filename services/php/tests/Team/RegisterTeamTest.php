<?php

declare(strict_types=1);

namespace App\Tests\Team;

use App\Team\Application\Command\RegisterTeam;
use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\ValueObject\TeamId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class RegisterTeamTest extends KernelTestCase
{
    public function test_registering_a_team_makes_the_creator_its_first_member(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var MessageBusInterface $commandBus */
        $commandBus = $container->get('command.bus');
        /** @var TeamRepository $teams */
        $teams = $container->get(TeamRepository::class);

        $envelope = $commandBus->dispatch(new RegisterTeam('Podium Team', 'user-1'));
        $teamId = $envelope->last(HandledStamp::class)->getResult();

        self::assertIsString($teamId);

        $team = $teams->get(TeamId::fromString($teamId));
        self::assertSame('Podium Team', $team->name()->toString());
        self::assertCount(1, $team->members());
        self::assertSame('user-1', $team->members()[0]->toString());
    }
}
