<?php

declare(strict_types=1);

namespace Tests\Functional\Team;

use Symfony\Component\HttpFoundation\Response;
use Tests\Functional\FunctionalTestCase;

final class ListTeamsTest extends FunctionalTestCase
{
    public function testListsOnlyTeamsWhereTheAuthenticatedUserIsAMember(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team', 'creatorUserId' => 'user-1']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $teamId = $this->jsonResponse()['id'];

        $this->getJson('/api/teams', bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $teams = $this->jsonResponse();
        self::assertCount(1, $teams);
        self::assertSame($teamId, $teams[0]['id']);
        self::assertSame('Podium Team', $teams[0]['name']);
    }

    public function testReturnsAnEmptyListForAUserWithNoTeams(): void
    {
        $this->getJson('/api/teams', bearerToken: 'user-without-teams');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame([], $this->jsonResponse());
    }

    public function testRejectsRequestsWithoutABearerToken(): void
    {
        $this->getJson('/api/teams');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
