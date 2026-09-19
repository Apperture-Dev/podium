<?php

declare(strict_types=1);

namespace Tests\Functional\Team;

use App\Team\Application\ApplicationService;
use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\ValueObject\TeamId;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RegisterTeamTest extends KernelTestCase
{
    private ApplicationService $applicationService;
    private TeamRepository $teams;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $this->applicationService = self::getContainer()->get(ApplicationService::class);
        $this->teams = self::getContainer()->get(TeamRepository::class);
    }

    public function testRegisteringATeamMakesTheCreatorItsFirstMember(): void
    {
        $teamId = $this->applicationService->registerTeam('Podium Team', 'user-1');

        $team = $this->teams->get(TeamId::fromString($teamId));
        self::assertSame('Podium Team', $team->name()->toString());
        self::assertCount(1, $team->members());
        self::assertSame('user-1', $team->members()[0]->toString());
    }
}
