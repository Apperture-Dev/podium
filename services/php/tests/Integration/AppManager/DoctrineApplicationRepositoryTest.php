<?php

declare(strict_types=1);

namespace Tests\Integration\AppManager;

use App\AppManager\Domain\Application;
use App\AppManager\Domain\ApplicationState;
use App\AppManager\Domain\Port\ApplicationRepository;
use App\AppManager\Domain\ValueObject\ApplicationId;
use RuntimeException;
use Tests\Integration\IntegrationTestCase;

final class DoctrineApplicationRepositoryTest extends IntegrationTestCase
{
    private ApplicationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->getService(ApplicationRepository::class);
    }

    public function testItCanPersistAndRetrieveById(): void
    {
        $application = Application::register('backend', 'project-1', 'team-1', 'template-1');

        $this->repository->save($application);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($application->id());

        self::assertTrue($retrieved->id()->equals($application->id()));
        self::assertSame('backend', $retrieved->serviceName());
        self::assertSame(ApplicationState::Created, $retrieved->state());
    }

    public function testItCanBeFoundByProjectIdAndServiceName(): void
    {
        $application = Application::register('backend', 'project-1', 'team-1', 'template-1');
        $this->repository->save($application);
        $this->clearEntityManager();

        $retrieved = $this->repository->findByProjectIdAndServiceName('project-1', 'backend');

        self::assertNotNull($retrieved);
        self::assertTrue($retrieved->id()->equals($application->id()));
    }

    public function testFindByProjectIdAndServiceNameReturnsNullWhenNotFound(): void
    {
        self::assertNull($this->repository->findByProjectIdAndServiceName('project-x', 'unknown'));
    }

    public function testStateChangesArePersisted(): void
    {
        $application = Application::register('backend', 'project-1', 'team-1', 'template-1');
        $application->markSourceChanged('rev-1', 'https://github.com/team/repo', 'github');
        $this->repository->save($application);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($application->id());

        self::assertSame(ApplicationState::Building, $retrieved->state());
        self::assertSame($application->version(), $retrieved->version());
    }

    public function testGetThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);

        $this->repository->get(ApplicationId::generate());
    }

    public function testFindByProjectIdReturnsOnlyApplicationsOfThatProject(): void
    {
        $application = Application::register('backend', 'project-1', 'team-1', 'template-1');
        $otherProjectApplication = Application::register('backend', 'project-2', 'team-1', 'template-1');
        $this->repository->save($application);
        $this->repository->save($otherProjectApplication);
        $this->clearEntityManager();

        $found = $this->repository->findByProjectId('project-1');

        self::assertCount(1, $found);
        self::assertTrue($found[0]->id()->equals($application->id()));
    }
}
