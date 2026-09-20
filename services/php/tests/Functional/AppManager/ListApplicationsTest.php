<?php

declare(strict_types=1);

namespace Tests\Functional\AppManager;

use App\AppManager\Application\ApplicationService as AppManagerApplicationService;
use Symfony\Component\HttpFoundation\Response;
use Tests\Functional\FunctionalTestCase;

final class ListApplicationsTest extends FunctionalTestCase
{
    public function testListsApplicationsForAProjectTheUserCanAccess(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team', 'creatorUserId' => 'user-1']);
        $teamId = $this->jsonResponse()['id'];

        $this->postJson('/api/projects', ['repositoryUrl' => 'https://github.com/team/repo', 'teamId' => $teamId, 'name' => 'Podium Backend']);
        $projectId = $this->jsonResponse()['id'];

        $appManager = static::getContainer()->get(AppManagerApplicationService::class);
        $appManager->registerApplication('backend', $projectId, 'php', 'symfony', 'rev-1', 'https://github.com/team/repo', 'github');

        $this->getJson('/api/teams/'.$teamId.'/projects/'.$projectId.'/applications', bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $applications = $this->jsonResponse();
        self::assertCount(1, $applications);
        self::assertSame('backend', $applications[0]['serviceName']);
        self::assertSame($projectId, $applications[0]['projectId']);
        self::assertSame('symfony', $applications[0]['framework']);
        self::assertNotEmpty($applications[0]['createdAt']);
    }

    public function testRejectsAUserWhoIsNotAMemberOfTheOwningTeam(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team', 'creatorUserId' => 'user-1']);
        $teamId = $this->jsonResponse()['id'];

        $this->postJson('/api/projects', ['repositoryUrl' => 'https://github.com/team/repo', 'teamId' => $teamId, 'name' => 'Podium Backend']);
        $projectId = $this->jsonResponse()['id'];

        $this->getJson('/api/teams/'.$teamId.'/projects/'.$projectId.'/applications', bearerToken: 'user-2');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testReturns404WhenTheProjectDoesNotBelongToThatTeam(): void
    {
        $this->postJson('/api/teams', ['name' => 'Team A', 'creatorUserId' => 'user-1']);
        $teamAId = $this->jsonResponse()['id'];

        $this->postJson('/api/teams', ['name' => 'Team B', 'creatorUserId' => 'user-2']);
        $teamBId = $this->jsonResponse()['id'];

        $this->postJson('/api/projects', ['repositoryUrl' => 'https://github.com/team/repo', 'teamId' => $teamAId, 'name' => 'Podium Backend']);
        $projectId = $this->jsonResponse()['id'];

        // user-2 es miembro real de teamB, pero el project es de teamA — la URL no debe existir.
        $this->getJson('/api/teams/'.$teamBId.'/projects/'.$projectId.'/applications', bearerToken: 'user-2');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testReturns404ForAnUnknownProjectId(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team', 'creatorUserId' => 'user-1']);
        $teamId = $this->jsonResponse()['id'];

        $this->getJson('/api/teams/'.$teamId.'/projects/01997e3a-0000-7000-8000-000000000000/applications', bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRejectsRequestsWithoutABearerToken(): void
    {
        $this->getJson('/api/teams/01997e3a-0000-7000-8000-000000000000/projects/01997e3a-0000-7000-8000-000000000000/applications');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
