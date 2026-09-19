<?php

declare(strict_types=1);

namespace Tests\Integration\Project;

use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\DeclaredService;
use App\Project\Domain\ValueObject\ProjectId;
use App\Project\Domain\ValueObject\ProjectName;
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
        $project = Project::register('https://github.com/team/repo', 'team-1', ProjectName::fromString('Test Project'));

        $this->repository->save($project);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($project->id());

        self::assertTrue($retrieved->id()->equals($project->id()));
        self::assertSame($project->hash()->toString(), $retrieved->hash()->toString());
        self::assertSame('Test Project', $retrieved->name()->toString());
        self::assertSame('team-1', $retrieved->teamId());
        self::assertSame('https://github.com/team/repo', $retrieved->repositoryUrl());
    }

    public function testGetThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);

        $this->repository->get(ProjectId::generate());
    }

    public function testFindByTeamIdReturnsOnlyProjectsOfThatTeam(): void
    {
        $project = Project::register('https://github.com/team/repo', 'team-1', ProjectName::fromString('Test Project'));
        $otherTeamProject = Project::register('https://github.com/other/repo', 'team-2', ProjectName::fromString('Other Project'));
        $this->repository->save($project);
        $this->repository->save($otherTeamProject);
        $this->clearEntityManager();

        $found = $this->repository->findByTeamId('team-1');

        self::assertCount(1, $found);
        self::assertTrue($found[0]->id()->equals($project->id()));
    }

    public function testKnownServiceNamesArePersisted(): void
    {
        $project = Project::register('https://github.com/team/repo', 'team-1', ProjectName::fromString('Test Project'));
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
        $project = Project::register('https://github.com/team/repo', 'team-1', ProjectName::fromString('Test Project'));
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
