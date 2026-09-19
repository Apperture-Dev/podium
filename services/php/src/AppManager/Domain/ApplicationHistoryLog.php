<?php

declare(strict_types=1);

namespace App\AppManager\Domain;

use App\AppManager\Domain\ValueObject\ApplicationDTO;
use Symfony\Component\Uid\Uuid;

/**
 * Auditoría append-only de toda mutación de Application. Aggregate propio,
 * pero solo Application puede crear una instancia — no tiene fábrica pública
 * pensada para llamarse desde fuera (convención, no forzable por el lenguaje).
 */
final readonly class ApplicationHistoryLog
{
    private function __construct(
        private Uuid $id,
        private string $serviceName,
        private ApplicationDTO $before,
        private ApplicationDTO $after,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    /** @internal solo Application debe llamar esto */
    public static function record(string $serviceName, ApplicationDTO $before, ApplicationDTO $after): self
    {
        return new self(Uuid::v7(), $serviceName, $before, $after, new \DateTimeImmutable());
    }

    public static function rehydrate(
        Uuid $id,
        string $serviceName,
        ApplicationDTO $before,
        ApplicationDTO $after,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $serviceName, $before, $after, $createdAt);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function serviceName(): string
    {
        return $this->serviceName;
    }

    public function before(): ApplicationDTO
    {
        return $this->before;
    }

    public function after(): ApplicationDTO
    {
        return $this->after;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
