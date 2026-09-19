<?php

declare(strict_types=1);

namespace Tests\Functional\Team;

use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\ValueObject\TeamId;
use Symfony\Component\HttpFoundation\Response;
use Tests\Functional\FunctionalTestCase;

final class RegisterTeamTest extends FunctionalTestCase
{
    public function testRegisteringATeamMakesTheCreatorItsFirstMember(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team', 'creatorUserId' => 'user-1']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $teamId = $this->jsonResponse()['id'];

        $teams = static::getContainer()->get(TeamRepository::class);
        $team = $teams->get(TeamId::fromString($teamId));

        self::assertSame('Podium Team', $team->name()->toString());
        self::assertCount(1, $team->members());
        self::assertSame('user-1', $team->members()[0]->toString());
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->postJson('/api/teams', ['name' => '', 'creatorUserId' => 'user-1']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
