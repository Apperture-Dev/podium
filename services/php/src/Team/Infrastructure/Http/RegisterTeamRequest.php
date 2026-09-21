<?php

declare(strict_types=1);

namespace App\Team\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterTeamRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 150)]
        public string $name,
    ) {
    }
}
