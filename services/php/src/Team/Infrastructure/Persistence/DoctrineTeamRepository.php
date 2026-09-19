<?php

declare(strict_types=1);

namespace App\Team\Infrastructure\Persistence;

use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\Team;
use App\Team\Domain\ValueObject\TeamId;
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

    public function save(Team $team): void
    {
        $this->entityManager->persist($team);
        $this->entityManager->flush();
    }
}
