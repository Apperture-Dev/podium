<?php

declare(strict_types=1);

namespace Tests\Integration\Project;

use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\DeclaredService;
use App\Project\Domain\ValueObject\ProjectId;
use RuntimeException;
use Tests\Integration\IntegrationTestCase;

final class DoctrineProjectRepositoryTest extends IntegrationTestCase
{
    private ProjectRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->getService(ProjectRepository::class);
    }

    public function testItCanPersistAndRetrieveById(): void
    {
        $project = Project::register('https://github.com/team/repo', 'team-1');

        $this->repository->save($project);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($project->id());

        self::assertTrue($retrieved->id()->equals($project->id()));
        self::assertSame($project->hash()->toString(), $retrieved->hash()->toString());
        self::assertSame('team-1', $retrieved->teamId());
        self::assertSame('https://github.com/team/repo', $retrieved->repositoryUrl());
    }

    public function testGetThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);

        $this->repository->get(ProjectId::generate());
    }

    public function testKnownServiceNamesArePersisted(): void
    {
        $project = Project::register('https://github.com/team/repo', 'team-1');
        $project->processSourceChanged('rev-1', 'https://github.com/team/repo', 'github', [
            new DeclaredService('backend', 'php', 'symfony'),
            new DeclaredService('frontend', 'typescript', 'react'),
        ]);

        $this->repository->save($project);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($project->id());

        self::assertSame(['backend', 'frontend'], $retrieved->knownServiceNames());
    }

    public function testSavingAnAlreadyPersistedProjectUpdatesIt(): void
    {
        $project = Project::register('https://github.com/team/repo', 'team-1');
        $this->repository->save($project);

        $project->processSourceChanged('rev-1', 'https://github.com/team/repo', 'github', [
            new DeclaredService('backend', 'php', 'symfony'),
        ]);
        $this->repository->save($project);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($project->id());

        self::assertSame(['backend'], $retrieved->knownServiceNames());
    }
}
