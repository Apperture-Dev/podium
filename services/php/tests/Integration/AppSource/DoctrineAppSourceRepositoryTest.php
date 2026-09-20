<?php

declare(strict_types=1);

namespace Tests\Integration\AppSource;

use App\AppSource\Domain\AppSource;
use App\AppSource\Domain\Port\AppSourceRepository;
use App\AppSource\Domain\ValueObject\AppSourceId;
use RuntimeException;
use Tests\Integration\IntegrationTestCase;

final class DoctrineAppSourceRepositoryTest extends IntegrationTestCase
{
    private AppSourceRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->getService(AppSourceRepository::class);
    }

    public function testItCanPersistAndRetrieveById(): void
    {
        $appSource = AppSource::register('project-1', 'https://github.com/team/repo', 'github');

        $this->repository->save($appSource);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($appSource->id());

        self::assertTrue($retrieved->id()->equals($appSource->id()));
        self::assertSame('project-1', $retrieved->projectId());
        self::assertSame('https://github.com/team/repo', $retrieved->repositoryUrl());
        self::assertSame('github', $retrieved->provider());
        self::assertNull($retrieved->revision());
    }

    public function testRevisionIsPersistedAfterRecording(): void
    {
        $appSource = AppSource::register('project-1', 'https://github.com/team/repo', 'github');
        $appSource->recordRevision('rev-1');

        $this->repository->save($appSource);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($appSource->id());

        self::assertSame('rev-1', $retrieved->revision());
    }

    public function testGetThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);

        $this->repository->get(AppSourceId::generate());
    }

    public function testFindAllReturnsEveryTrackedAppSource(): void
    {
        $first = AppSource::register('project-1', 'https://github.com/team/repo-one', 'github');
        $second = AppSource::register('project-2', 'https://github.com/team/repo-two', 'github');
        $this->repository->save($first);
        $this->repository->save($second);
        $this->clearEntityManager();

        $all = $this->repository->findAll();

        $ids = array_map(static fn (AppSource $appSource): string => $appSource->id()->toString(), $all);
        self::assertContains($first->id()->toString(), $ids);
        self::assertContains($second->id()->toString(), $ids);
    }
}
