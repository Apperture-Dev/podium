<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Persistence;

use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\ProjectId;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final class DoctrineProjectRepository implements ProjectRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(ProjectId $id): Project
    {
        return $this->entityManager->find(Project::class, $id)
            ?? throw new RuntimeException(\sprintf('Project "%s" not found.', $id->toString()));
    }

    public function findByTeamId(string $teamId): array
    {
        return $this->entityManager->getRepository(Project::class)->findBy(['teamId' => $teamId]);
    }

    public function save(Project $project): void
    {
        $this->entityManager->persist($project);
        $this->entityManager->flush();
    }
}
