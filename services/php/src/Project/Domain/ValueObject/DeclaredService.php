<?php

declare(strict_types=1);

namespace App\Project\Domain\ValueObject;

use InvalidArgumentException;

final readonly class DeclaredService
{
    public function __construct(
        public string $serviceName,
        public string $lang,
        public string $framework,
    ) {
        if ('' === trim($serviceName)) {
            throw new InvalidArgumentException('DeclaredService.serviceName cannot be empty.');
        }
    }
}
