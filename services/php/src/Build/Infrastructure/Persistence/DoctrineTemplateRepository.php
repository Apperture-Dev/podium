<?php

declare(strict_types=1);

namespace App\Build\Infrastructure\Persistence;

use App\Build\Domain\Port\TemplateRepository;
use App\Build\Domain\Template;
use App\Build\Domain\ValueObject\TemplateId;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final class DoctrineTemplateRepository implements TemplateRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function get(TemplateId $id): Template
    {
        return $this->entityManager->find(Template::class, $id)
            ?? throw new RuntimeException(\sprintf('Template "%s" not found.', $id->toString()));
    }

    public function save(Template $template): void
    {
        $this->entityManager->persist($template);
        $this->entityManager->flush();
    }
}
