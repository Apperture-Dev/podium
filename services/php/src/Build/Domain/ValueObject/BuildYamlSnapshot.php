<?php

declare(strict_types=1);

namespace App\Build\Domain\ValueObject;

/**
 * El yaml (podium.yaml) resuelto de un BuildJob, leído por el propio Job de
 * Kubernetes (nunca por Build) — se recibe en `JobSucceeded`.
 *
 * Solo guarda lo que es de construcción. Lo que la aplicación necesita para
 * correr — env vars de runtime, base de datos — lo lee Deploy del mismo yaml
 * en la misma revisión: Build hace una imagen y no tiene por qué saber qué
 * necesita esa imagen para funcionar.
 */
final readonly class BuildYamlSnapshot
{
    /** @param array<string, string> $buildEnvVars */
    public function __construct(
        public array $buildEnvVars,
    ) {
    }

    /** @return array{buildEnvVars: array<string,string>} */
    public function toArray(): array
    {
        return [
            'buildEnvVars' => $this->buildEnvVars,
        ];
    }

    /** @param array{buildEnvVars: array<string,string>} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['buildEnvVars']);
    }
}
