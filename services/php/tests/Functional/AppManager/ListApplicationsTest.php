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
        $appManager->registerApplication('backend', $projectId, 'php', 'symfony');

        $this->getJson('/api/applications?projectId='.$projectId, bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $applications = $this->jsonResponse();
        self::assertCount(1, $applications);
        self::assertSame('backend', $applications[0]['serviceName']);
        self::assertSame($projectId, $applications[0]['projectId']);
    }

    public function testRejectsAUserWhoIsNotAMemberOfTheOwningTeam(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team', 'creatorUserId' => 'user-1']);
        $teamId = $this->jsonResponse()['id'];

        $this->postJson('/api/projects', ['repositoryUrl' => 'https://github.com/team/repo', 'teamId' => $teamId, 'name' => 'Podium Backend']);
        $projectId = $this->jsonResponse()['id'];

        $this->getJson('/api/applications?projectId='.$projectId, bearerToken: 'user-2');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testReturns404ForAnUnknownProjectId(): void
    {
        $this->getJson('/api/applications?projectId=01997e3a-0000-7000-8000-000000000000', bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRejectsRequestsWithoutABearerToken(): void
    {
        $this->getJson('/api/applications?projectId=01997e3a-0000-7000-8000-000000000000');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
