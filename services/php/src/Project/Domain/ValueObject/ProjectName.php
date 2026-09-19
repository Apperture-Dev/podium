<?php

declare(strict_types=1);

namespace App\Project\Domain\ValueObject;

use InvalidArgumentException;

final readonly class ProjectName
{
    private const MAX_LENGTH = 150;

    private function __construct(private string $value)
    {
        if ('' === trim($value)) {
            throw new InvalidArgumentException('ProjectName cannot be empty.');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidArgumentException(\sprintf('ProjectName cannot exceed %d characters.', self::MAX_LENGTH));
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
}
