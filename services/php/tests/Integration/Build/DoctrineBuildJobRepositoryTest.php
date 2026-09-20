<?php

declare(strict_types=1);

namespace Tests\Integration\Build;

use App\Build\Domain\BuildJob;
use App\Build\Domain\BuildStatus;
use App\Build\Domain\Port\BuildJobRepository;
use App\Build\Domain\ValueObject\BuildJobId;
use RuntimeException;
use Tests\Integration\IntegrationTestCase;

final class DoctrineBuildJobRepositoryTest extends IntegrationTestCase
{
    private BuildJobRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->getService(BuildJobRepository::class);
    }

    public function testItCanPersistAndRetrieveById(): void
    {
        $buildJob = BuildJob::request('team-1', 'backend', 'project-1', 'template-1', 'ghcr.io/podium/buildah-node:latest', 'v1', 'rev-1', 'https://github.com/team/repo', 'github');

        $this->repository->save($buildJob);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($buildJob->id());

        self::assertTrue($retrieved->id()->equals($buildJob->id()));
        self::assertSame('backend', $retrieved->serviceName());
        self::assertSame(BuildStatus::Pending, $retrieved->status());
    }

    public function testCompletingABuildJobPersistsTheYamlSnapshot(): void
    {
        $buildJob = BuildJob::request('team-1', 'backend', 'project-1', 'template-1', 'ghcr.io/podium/buildah-node:latest', 'v1', 'rev-1', 'https://github.com/team/repo', 'github');
        $buildJob->completeBuildJob('registry/backend:rev-1', 3000, ['NPM_TOKEN' => 'x']);
        $this->repository->save($buildJob);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($buildJob->id());

        self::assertSame(BuildStatus::Succeeded, $retrieved->status());
        self::assertSame('registry/backend:rev-1', $retrieved->image());
        self::assertSame(['NPM_TOKEN' => 'x'], $retrieved->yamlSnapshot()?->buildEnvVars);
    }

    public function testGetThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);

        $this->repository->get(BuildJobId::generate());
    }
}
