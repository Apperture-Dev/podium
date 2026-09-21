<?php

declare(strict_types=1);

namespace Tests\Functional\Team;

use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\ValueObject\TeamId;
use App\Team\Domain\ValueObject\UserId;
use Symfony\Component\HttpFoundation\Response;
use Tests\Functional\FunctionalTestCase;

final class RegisterTeamTest extends FunctionalTestCase
{
    public function testRegisteringATeamMakesTheAuthenticatedCallerItsFirstMember(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team'], 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $teamId = $this->jsonResponse()['id'];

        $teams = static::getContainer()->get(TeamRepository::class);
        $team = $teams->get(TeamId::fromString($teamId));

        self::assertSame('Podium Team', $team->name()->toString());
        self::assertCount(1, $team->members());
        self::assertSame('user-1', $team->members()[0]->toString());
    }

    public function testTheCreatedTeamIsImmediatelyVisibleToItsCreator(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team'], 'user-1');
        $teamId = $this->jsonResponse()['id'];

        $this->getJson('/api/teams', bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([['id' => $teamId, 'name' => 'Podium Team']], $this->jsonResponse());
    }

    /**
     * El dueño sale del token y de ningún otro sitio. Este test es la razón por
     * la que el campo desapareció del request object: mientras se pudiera
     * afirmar desde fuera, la autorización de lectura de todos los GET era
     * decorativa.
     */
    public function testACreatorIdentitySuppliedInTheBodyIsNotHonored(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team', 'creatorUserId' => 'someone-else'], 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $teams = static::getContainer()->get(TeamRepository::class);
        $team = $teams->get(TeamId::fromString($this->jsonResponse()['id']));

        self::assertCount(1, $team->members());
        self::assertSame('user-1', $team->members()[0]->toString());
        self::assertSame([], $teams->findByUserId(UserId::fromString('someone-else')));
    }

    public function testRejectsARegistrationWithoutABearerToken(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team']);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $teams = static::getContainer()->get(TeamRepository::class);
        self::assertSame([], $teams->findByUserId(UserId::fromString('user-1')));
    }

    public function testRejectsARegistrationWithAnInvalidToken(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team'], '   ');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->postJson('/api/teams', ['name' => ''], 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
