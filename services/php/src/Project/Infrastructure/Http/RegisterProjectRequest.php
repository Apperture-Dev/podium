<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Http;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterProjectRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Url]
        public string $repositoryUrl,
        #[Assert\NotBlank]
        public string $teamId,
    ) {
    }
}
