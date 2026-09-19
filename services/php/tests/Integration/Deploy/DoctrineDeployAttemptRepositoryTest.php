<?php

declare(strict_types=1);

namespace Tests\Integration\Deploy;

use App\Deploy\Domain\DeployAttempt;
use App\Deploy\Domain\DeployStatus;
use App\Deploy\Domain\Port\DeployAttemptRepository;
use App\Deploy\Domain\ValueObject\DeployAttemptId;
use RuntimeException;
use Tests\Integration\IntegrationTestCase;

final class DoctrineDeployAttemptRepositoryTest extends IntegrationTestCase
{
    private DeployAttemptRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->getService(DeployAttemptRepository::class);
    }

    public function testItCanPersistAndRetrieveById(): void
    {
        $deployAttempt = DeployAttempt::request('team-1', 'backend', 'project-1', 'v1', 'registry/backend:rev-1', ['API_URL' => 'https://x'], []);

        $this->repository->save($deployAttempt);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($deployAttempt->id());

        self::assertTrue($retrieved->id()->equals($deployAttempt->id()));
        self::assertSame('backend', $retrieved->serviceName());
        self::assertSame(DeployStatus::Pending, $retrieved->status());
        self::assertSame('registry/backend:rev-1', $retrieved->values()->image);
        self::assertSame(['API_URL' => 'https://x'], $retrieved->values()->envVars);
    }

    public function testFailingADeployAttemptPersistsTheRetryCount(): void
    {
        $deployAttempt = DeployAttempt::request('team-1', 'backend', 'project-1', 'v1', 'registry/backend:rev-1', [], []);
        $deployAttempt->failDeployAttempt('reintentos agotados', 3);
        $this->repository->save($deployAttempt);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($deployAttempt->id());

        self::assertSame(DeployStatus::Failed, $retrieved->status());
        self::assertSame('reintentos agotados', $retrieved->errorMessage());
        self::assertSame(3, $retrieved->retryCount());
    }

    public function testGetThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);

        $this->repository->get(DeployAttemptId::generate());
    }
}
