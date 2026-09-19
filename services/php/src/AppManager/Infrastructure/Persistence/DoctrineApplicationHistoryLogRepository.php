<?php

declare(strict_types=1);

namespace App\AppManager\Infrastructure\Persistence;

use App\AppManager\Domain\ApplicationHistoryLog;
use App\AppManager\Domain\Port\ApplicationHistoryLogRepository;
use App\AppManager\Domain\ValueObject\ApplicationId;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineApplicationHistoryLogRepository implements ApplicationHistoryLogRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findByApplicationId(ApplicationId $applicationId): array
    {
        return $this->entityManager->getRepository(ApplicationHistoryLog::class)->findBy(
            ['applicationId' => $applicationId],
            ['createdAt' => 'ASC'],
        );
    }
}
