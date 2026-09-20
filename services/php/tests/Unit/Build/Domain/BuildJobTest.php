<?php

declare(strict_types=1);

namespace Tests\Unit\Build\Domain;

use App\Build\Domain\BuildJob;
use App\Build\Domain\BuildStatus;
use App\Build\Domain\Event\BuildFailed;
use App\Build\Domain\Event\BuildJobRequested;
use App\Build\Domain\Event\BuildSucceeded;
use Tests\Unit\UnitTestCase;

final class BuildJobTest extends UnitTestCase
{
    private BuildJob $buildJob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildJob = BuildJob::request(
            'team-1',
            'backend',
            'project-1',
            'template-1',
            'ghcr.io/podium/buildah-node:latest',
            'v1',
            'rev-1',
            'https://github.com/team/repo',
            'github',
        );
        $this->buildJob->releaseEvents();
    }

    public function testRequestStartsInPendingAndPublishesBuildJobRequested(): void
    {
        $buildJob = BuildJob::request('team-1', 'backend', 'project-1', 'template-1', 'ghcr.io/podium/buildah-node:latest', 'v1', 'rev-1', 'https://github.com/team/repo', 'github');

        $events = $buildJob->releaseEvents();

        self::assertSame(BuildStatus::Pending, $buildJob->status());
        self::assertCount(1, $events);
        self::assertInstanceOf(BuildJobRequested::class, $events[0]);
        self::assertSame($buildJob->id()->toString(), $events[0]->buildJobId);
        self::assertSame('ghcr.io/podium/buildah-node:latest', $events[0]->jobImage);
        self::assertSame([], $events[0]->command);
        self::assertSame('backend', $events[0]->envVars['SERVICE_NAME']);
        self::assertSame('rev-1', $events[0]->envVars['COMMIT_ID']);
    }

    public function testCompleteBuildJobMovesToSucceededAndPublishesBuildSucceeded(): void
    {
        $events = $this->buildJob->completeBuildJob('registry/backend:rev-1', 3000, ['NPM_TOKEN' => 'x'], ['API_URL' => 'https://x'], []);

        self::assertSame(BuildStatus::Succeeded, $this->buildJob->status());
        self::assertSame('registry/backend:rev-1', $this->buildJob->image());
        self::assertSame(['API_URL' => 'https://x'], $this->buildJob->yamlSnapshot()?->deployEnvVars);
        self::assertCount(1, $events);
        self::assertInstanceOf(BuildSucceeded::class, $events[0]);
        self::assertSame('registry/backend:rev-1', $events[0]->image);
        self::assertSame(3000, $events[0]->port);
        self::assertSame(['API_URL' => 'https://x'], $events[0]->deployEnvVars);
    }

    public function testFailBuildJobMovesToFailedAndPublishesBuildFailed(): void
    {
        $events = $this->buildJob->failBuildJob('podium.yaml no valida contra el paramSchema');

        self::assertSame(BuildStatus::Failed, $this->buildJob->status());
        self::assertSame('podium.yaml no valida contra el paramSchema', $this->buildJob->errorMessage());
        self::assertCount(1, $events);
        self::assertInstanceOf(BuildFailed::class, $events[0]);
        self::assertSame('podium.yaml no valida contra el paramSchema', $events[0]->errorMessage);
    }
}
