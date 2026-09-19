<?php

declare(strict_types=1);

namespace Tests\Integration\Team;

use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\Team;
use App\Team\Domain\ValueObject\TeamId;
use App\Team\Domain\ValueObject\TeamName;
use App\Team\Domain\ValueObject\UserId;
use RuntimeException;
use Tests\Integration\IntegrationTestCase;

final class DoctrineTeamRepositoryTest extends IntegrationTestCase
{
    private TeamRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->getService(TeamRepository::class);
    }

    public function testItCanPersistAndRetrieveById(): void
    {
        $team = Team::register(TeamName::fromString('Podium Team'), UserId::fromString('user-1'));

        $this->repository->save($team);
        $this->clearEntityManager();

        $retrieved = $this->repository->get($team->id());

        self::assertTrue($retrieved->id()->equals($team->id()));
        self::assertSame('Podium Team', $retrieved->name()->toString());
        self::assertCount(1, $retrieved->members());
        self::assertSame('user-1', $retrieved->members()[0]->toString());
    }

    public function testGetThrowsWhenNotFound(): void
    {
        $this->expectException(RuntimeException::class);

        $this->repository->get(TeamId::generate());
    }
}
