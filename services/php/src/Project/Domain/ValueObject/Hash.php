<?php

declare(strict_types=1);

namespace App\Project\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Compone la URL pública de cada servicio del Project.
 * La regla de generación real está sin discutir todavía (project/discovery.md:28) —
 * esta es una implementación placeholder (8 chars, alfanumérico en minúscula), a
 * revisar en una sesión de discovery antes de depender de ella para colisiones/DNS reales.
 */
final readonly class Hash
{
    private const PATTERN = '/^[a-z0-9]{8}$/';

    private function __construct(private string $value)
    {
        if (1 !== preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(\sprintf('Invalid Project hash: "%s".', $value));
        }
    }

    public static function generate(): self
    {
        return new self(substr(bin2hex(random_bytes(5)), 0, 8));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
