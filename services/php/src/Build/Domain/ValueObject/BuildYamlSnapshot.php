<?php

declare(strict_types=1);

namespace App\Build\Domain\ValueObject;

/**
 * El yaml (podium.yaml) resuelto de un BuildJob, leído y validado por el
 * propio Job de Kubernetes (nunca por Build) — se recibe entero en
 * `JobSucceeded`. buildEnvVars es uso propio del Job; deployEnvVars y
 * databaseDeclaration se reparten hacia adelante en BuildSucceeded.
 */
final readonly class BuildYamlSnapshot
{
    public function __construct(
        /** @var array<string, string> */
        public array $buildEnvVars,
        /** @var array<string, string> */
        public array $deployEnvVars,
        /** @var array<string, string> */
        public array $databaseDeclaration,
    ) {
    }

    /** @return array{buildEnvVars: array<string,string>, deployEnvVars: array<string,string>, databaseDeclaration: array<string,string>} */
    public function toArray(): array
    {
        return [
            'buildEnvVars' => $this->buildEnvVars,
            'deployEnvVars' => $this->deployEnvVars,
            'databaseDeclaration' => $this->databaseDeclaration,
        ];
    }

    /** @param array{buildEnvVars: array<string,string>, deployEnvVars: array<string,string>, databaseDeclaration: array<string,string>} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['buildEnvVars'], $data['deployEnvVars'], $data['databaseDeclaration']);
    }
}
