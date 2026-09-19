<?php

declare(strict_types=1);

namespace App\Team\Domain\ValueObject;

final readonly class TeamDTO
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
