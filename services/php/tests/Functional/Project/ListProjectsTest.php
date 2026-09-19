<?php

declare(strict_types=1);

namespace Tests\Functional\Project;

use Symfony\Component\HttpFoundation\Response;
use Tests\Functional\FunctionalTestCase;

final class ListProjectsTest extends FunctionalTestCase
{
    public function testListsProjectsForATeamTheUserBelongsTo(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team', 'creatorUserId' => 'user-1']);
        $teamId = $this->jsonResponse()['id'];

        $this->postJson('/api/projects', ['repositoryUrl' => 'https://github.com/team/repo', 'teamId' => $teamId, 'name' => 'Podium Backend']);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->getJson('/api/projects?teamId='.$teamId, bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $projects = $this->jsonResponse();
        self::assertCount(1, $projects);
        self::assertSame('Podium Backend', $projects[0]['name']);
        self::assertSame($teamId, $projects[0]['teamId']);
    }

    public function testRejectsAUserWhoIsNotAMemberOfTheTeam(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team', 'creatorUserId' => 'user-1']);
        $teamId = $this->jsonResponse()['id'];

        $this->getJson('/api/projects?teamId='.$teamId, bearerToken: 'user-2');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testReturns404ForAnUnknownTeamId(): void
    {
        $this->getJson('/api/projects?teamId=01997e3a-0000-7000-8000-000000000000', bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRejectsRequestsWithoutABearerToken(): void
    {
        $this->getJson('/api/projects?teamId=01997e3a-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
