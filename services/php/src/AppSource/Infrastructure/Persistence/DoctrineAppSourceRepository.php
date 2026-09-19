<?php

declare(strict_types=1);

namespace App\AppSource\Infrastructure\Persistence;

use App\AppSource\Domain\AppSource;
use App\AppSource\Domain\Port\AppSourceRepository;
use App\AppSource\Domain\ValueObject\AppSourceId;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final class DoctrineAppSourceRepository implements AppSourceRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(AppSourceId $id): AppSource
    {
        return $this->entityManager->find(AppSource::class, $id)
            ?? throw new RuntimeException(\sprintf('AppSource "%s" not found.', $id->toString()));
    }

    public function save(AppSource $appSource): void
    {
        $this->entityManager->persist($appSource);
        $this->entityManager->flush();
    }
}
