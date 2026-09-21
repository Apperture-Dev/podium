<?php

declare(strict_types=1);

namespace Tests\Functional\Team;

use Symfony\Component\HttpFoundation\Response;
use Tests\Functional\FunctionalTestCase;

final class GetTeamTest extends FunctionalTestCase
{
    public function testGetsATeamTheUserIsAMemberOf(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team'], 'user-1');
        $teamId = $this->jsonResponse()['id'];

        $this->getJson('/api/teams/'.$teamId, bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertSame(['id' => $teamId, 'name' => 'Podium Team'], $this->jsonResponse());
    }

    public function testRejectsAUserWhoIsNotAMember(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team'], 'user-1');
        $teamId = $this->jsonResponse()['id'];

        $this->getJson('/api/teams/'.$teamId, bearerToken: 'user-2');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testReturns404ForAnUnknownTeamId(): void
    {
        $this->getJson('/api/teams/01997e3a-0000-7000-8000-000000000000', bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRejectsRequestsWithoutABearerToken(): void
    {
        $this->getJson('/api/teams/01997e3a-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
