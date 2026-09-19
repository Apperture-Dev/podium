<?php

declare(strict_types=1);

namespace App\Team\Infrastructure\Persistence;

use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\Team;
use App\Team\Domain\ValueObject\TeamId;
use App\Team\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final class DoctrineTeamRepository implements TeamRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(TeamId $id): Team
    {
        return $this->entityManager->find(Team::class, $id)
            ?? throw new RuntimeException(\sprintf('Team "%s" not found.', $id->toString()));
    }

    /**
     * `members` es un array JSON (ver UserIdListType) — sin relación que
     * Doctrine pueda consultar por columna, así que se filtra con el
     * operador de contención jsonb de Postgres.
     *
     * @return list<Team>
     */
    public function findByUserId(UserId $userId): array
    {
        $ids = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT id FROM teams WHERE members::jsonb @> :member::jsonb',
            ['member' => json_encode([$userId->toString()], \JSON_THROW_ON_ERROR)],
        );

        return array_map(fn (string $id): Team => $this->get(TeamId::fromString($id)), $ids);
    }

    public function save(Team $team): void
    {
        $this->entityManager->persist($team);
        $this->entityManager->flush();
    }
}
