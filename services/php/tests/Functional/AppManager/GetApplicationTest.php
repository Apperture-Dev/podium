<?php

declare(strict_types=1);

namespace Tests\Functional\AppManager;

use App\AppManager\Application\ApplicationService as AppManagerApplicationService;
use Symfony\Component\HttpFoundation\Response;
use Tests\Functional\FunctionalTestCase;

final class GetApplicationTest extends FunctionalTestCase
{
    public function testGetsAnApplicationByServiceName(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team'], 'user-1');
        $teamId = $this->jsonResponse()['id'];

        $this->postJson('/api/projects', ['repositoryUrl' => 'https://github.com/team/repo', 'teamId' => $teamId, 'name' => 'Podium Backend'], 'user-1');
        $projectId = $this->jsonResponse()['id'];

        $appManager = static::getContainer()->get(AppManagerApplicationService::class);
        $appManager->registerApplication('backend', $projectId, 'php', 'symfony', 'rev-1', 'https://github.com/team/repo', 'github');

        $this->getJson('/api/teams/'.$teamId.'/projects/'.$projectId.'/applications/backend', bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $application = $this->jsonResponse();
        self::assertSame('backend', $application['serviceName']);
        self::assertSame('Building', $application['state']);
        self::assertSame('symfony', $application['framework']);
        self::assertNotEmpty($application['createdAt']);
    }

    public function testRejectsAUserWhoIsNotAMemberOfTheOwningTeam(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team'], 'user-1');
        $teamId = $this->jsonResponse()['id'];

        $this->postJson('/api/projects', ['repositoryUrl' => 'https://github.com/team/repo', 'teamId' => $teamId, 'name' => 'Podium Backend'], 'user-1');
        $projectId = $this->jsonResponse()['id'];

        $appManager = static::getContainer()->get(AppManagerApplicationService::class);
        $appManager->registerApplication('backend', $projectId, 'php', 'symfony', 'rev-1', 'https://github.com/team/repo', 'github');

        $this->getJson('/api/teams/'.$teamId.'/projects/'.$projectId.'/applications/backend', bearerToken: 'user-2');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testReturns404ForAnUnknownServiceName(): void
    {
        $this->postJson('/api/teams', ['name' => 'Podium Team'], 'user-1');
        $teamId = $this->jsonResponse()['id'];

        $this->postJson('/api/projects', ['repositoryUrl' => 'https://github.com/team/repo', 'teamId' => $teamId, 'name' => 'Podium Backend'], 'user-1');
        $projectId = $this->jsonResponse()['id'];

        $this->getJson('/api/teams/'.$teamId.'/projects/'.$projectId.'/applications/unknown-service', bearerToken: 'user-1');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRejectsRequestsWithoutABearerToken(): void
    {
        $this->getJson('/api/teams/01997e3a-0000-7000-8000-000000000000/projects/01997e3a-0000-7000-8000-000000000000/applications/backend');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
