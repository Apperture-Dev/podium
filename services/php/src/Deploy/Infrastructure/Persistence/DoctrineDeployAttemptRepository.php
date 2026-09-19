<?php

declare(strict_types=1);

namespace App\Deploy\Infrastructure\Persistence;

use App\Deploy\Domain\DeployAttempt;
use App\Deploy\Domain\Port\DeployAttemptRepository;
use App\Deploy\Domain\ValueObject\DeployAttemptId;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final class DoctrineDeployAttemptRepository implements DeployAttemptRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(DeployAttemptId $id): DeployAttempt
    {
        return $this->entityManager->find(DeployAttempt::class, $id)
            ?? throw new RuntimeException(\sprintf('DeployAttempt "%s" not found.', $id->toString()));
    }

    public function save(DeployAttempt $deployAttempt): void
    {
        $this->entityManager->persist($deployAttempt);
        $this->entityManager->flush();
    }
}
