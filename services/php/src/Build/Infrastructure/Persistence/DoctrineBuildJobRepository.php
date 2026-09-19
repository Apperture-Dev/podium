<?php

declare(strict_types=1);

namespace App\Build\Infrastructure\Persistence;

use App\Build\Domain\BuildJob;
use App\Build\Domain\Port\BuildJobRepository;
use App\Build\Domain\ValueObject\BuildJobId;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final class DoctrineBuildJobRepository implements BuildJobRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(BuildJobId $id): BuildJob
    {
        return $this->entityManager->find(BuildJob::class, $id)
            ?? throw new RuntimeException(\sprintf('BuildJob "%s" not found.', $id->toString()));
    }

    public function save(BuildJob $buildJob): void
    {
        $this->entityManager->persist($buildJob);
        $this->entityManager->flush();
    }
}
