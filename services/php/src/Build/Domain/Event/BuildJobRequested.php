<?php

declare(strict_types=1);

namespace App\Build\Domain\Event;

final readonly class BuildJobRequested
{
    /**
     * @param list<string>          $command Siempre vacío hoy (MVP) — el jobImage usa su propio entrypoint, convención de Template
     * @param array<string, string> $envVars
     */
    public function __construct(
        public string $buildJobId,
        public string $jobImage,
        public array $command,
        public array $envVars,
    ) {
    }
}
