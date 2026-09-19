<?php

declare(strict_types=1);

namespace App\AppManager\Infrastructure\Persistence;

use App\AppManager\Domain\Application;
use App\AppManager\Domain\Port\ApplicationRepository;
use App\AppManager\Domain\ValueObject\ApplicationId;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final class DoctrineApplicationRepository implements ApplicationRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(ApplicationId $id): Application
    {
        return $this->entityManager->find(Application::class, $id)
            ?? throw new RuntimeException(\sprintf('Application "%s" not found.', $id->toString()));
    }

    public function findByProjectIdAndServiceName(string $projectId, string $serviceName): ?Application
    {
        return $this->entityManager->getRepository(Application::class)->findOneBy([
            'projectId' => $projectId,
            'serviceName' => $serviceName,
        ]);
    }

    public function save(Application $application): void
    {
        $this->entityManager->persist($application);

        foreach ($application->historyLogs() as $historyLog) {
            $this->entityManager->persist($historyLog);
        }

        $this->entityManager->flush();
    }
}
