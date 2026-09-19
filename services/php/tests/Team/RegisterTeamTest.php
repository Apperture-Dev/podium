<?php

declare(strict_types=1);

namespace App\Tests\Team;

use App\Team\Application\ApplicationService;
use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\ValueObject\TeamId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RegisterTeamTest extends KernelTestCase
{
    public function testRegisteringATeamMakesTheCreatorItsFirstMember(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $applicationService = $container->get(ApplicationService::class);
        $teams = $container->get(TeamRepository::class);

        $teamId = $applicationService->registerTeam('Podium Team', 'user-1');

        $team = $teams->get(TeamId::fromString($teamId));
        self::assertSame('Podium Team', $team->name()->toString());
        self::assertCount(1, $team->members());
        self::assertSame('user-1', $team->members()[0]->toString());
    }
}
