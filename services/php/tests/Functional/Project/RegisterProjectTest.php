<?php

declare(strict_types=1);

namespace Tests\Functional\Project;

use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\ValueObject\ProjectId;
use App\Team\Application\ApplicationService as TeamApplicationService;
use Symfony\Component\HttpFoundation\Response;
use Tests\Functional\FunctionalTestCase;

final class RegisterProjectTest extends FunctionalTestCase
{
    public function testRegisteringAProjectRequiresAnExistingTeam(): void
    {
        $teams = static::getContainer()->get(TeamApplicationService::class);
        $teamId = $teams->registerTeam('Podium Team', 'user-1');

        $this->postJson('/api/projects', ['repositoryUrl' => 'https://github.com/team/repo', 'teamId' => $teamId, 'name' => 'Podium Backend']);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $projectId = $this->jsonResponse()['id'];

        $projects = static::getContainer()->get(ProjectRepository::class);
        $project = $projects->get(ProjectId::fromString($projectId));

        self::assertSame('https://github.com/team/repo', $project->repositoryUrl());
        self::assertSame($teamId, $project->teamId());
        self::assertSame('Podium Backend', $project->name()->toString());
    }

    public function testRejectsAnUnknownTeamId(): void
    {
        $this->postJson('/api/projects', ['repositoryUrl' => 'https://github.com/team/repo', 'teamId' => '01997e3a-0000-7000-8000-000000000000', 'name' => 'Podium Backend']);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
