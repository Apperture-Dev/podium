<?php

declare(strict_types=1);

namespace Tests\Functional\Project;

use Symfony\Component\HttpFoundation\Response;
use Tests\Functional\FunctionalTestCase;

final class GetProjectTest extends FunctionalTestCase
{
    public function testGetsAProjectOfATeamTheUserBelongsTo(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team'], 'user-1');
        $teamId = $this->jsonResponse()['id'];

        $this->postJson('/api/projects', ['repositoryUrl' => 'https://github.com/team/repo', 'teamId' => $teamId, 'name' => 'Podium Backend'], 'user-1');
        $projectId = $this->jsonResponse()['id'];

        $this->getJson('/api/teams/'.$teamId.'/projects/'.$projectId, bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $project = $this->jsonResponse();
        self::assertSame($projectId, $project['id']);
        self::assertSame('Podium Backend', $project['name']);
        self::assertSame($teamId, $project['teamId']);
        self::assertNotEmpty($project['createdAt']);
    }

    public function testRejectsAUserWhoIsNotAMemberOfTheTeam(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team'], 'user-1');
        $teamId = $this->jsonResponse()['id'];

        $this->postJson('/api/projects', ['repositoryUrl' => 'https://github.com/team/repo', 'teamId' => $teamId, 'name' => 'Podium Backend'], 'user-1');
        $projectId = $this->jsonResponse()['id'];

        $this->getJson('/api/teams/'.$teamId.'/projects/'.$projectId, bearerToken: 'user-2');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testReturns404WhenTheProjectDoesNotBelongToThatTeam(): void
    {
        $this->postJson('/api/teams', ['name' => 'Team A'], 'user-1');
        $teamAId = $this->jsonResponse()['id'];

        $this->postJson('/api/teams', ['name' => 'Team B'], 'user-2');
        $teamBId = $this->jsonResponse()['id'];

        $this->postJson('/api/projects', ['repositoryUrl' => 'https://github.com/team/repo', 'teamId' => $teamAId, 'name' => 'Podium Backend'], 'user-1');
        $projectId = $this->jsonResponse()['id'];

        $this->getJson('/api/teams/'.$teamBId.'/projects/'.$projectId, bearerToken: 'user-2');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testReturns404ForAnUnknownProjectId(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team'], 'user-1');
        $teamId = $this->jsonResponse()['id'];

        $this->getJson('/api/teams/'.$teamId.'/projects/01997e3a-0000-7000-8000-000000000000', bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRejectsRequestsWithoutABearerToken(): void
    {
        $this->getJson('/api/teams/01997e3a-0000-7000-8000-000000000000/projects/01997e3a-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
