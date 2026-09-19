<?php

declare(strict_types=1);

namespace App\Team\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Referencia externa opaca al claim `sub` de un JWT de Keycloak — no modela
 * un aggregate `User` propio (misma decisión que `context-map.md`: userId
 * retirado del Shared Kernel, nunca un dominio propio). Se envuelve en VO
 * solo para evitar primitive obsession dentro de la lista de miembros.
 */
final readonly class UserId
{
    private function __construct(private string $value)
    {
        if ('' === trim($value)) {
            throw new InvalidArgumentException('UserId cannot be empty.');
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
