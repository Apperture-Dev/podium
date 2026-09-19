<?php

declare(strict_types=1);

namespace App\Team\Application\Command;

final readonly class RegisterTeam
{
    public function __construct(
        public string $name,
        public string $creatorUserId,
    ) {
    }
}
