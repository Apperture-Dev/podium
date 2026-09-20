<?php

declare(strict_types=1);

namespace App\Deploy\Domain\ValueObject;

/**
 * El conjunto de valores que se le pasan al chart genérico de ArgoCD
 * (`values`/`valuesObject`) para renderizar el Deployment real. Paralelo a
 * BuildYamlSnapshot en Build. No hay una imagen OCI de manifiestos por
 * build — el chart es genérico y se publica una sola vez, fuera del ciclo
 * de cada deploy (ver deploy/model.md).
 */
final readonly class DeployValues
{
    /** @param array<string, string> $envVars vacío por ahora: el chart todavía no inyecta las del equipo */
    public function __construct(
        public string $image,
        public array $envVars,
        public DatabaseDeclaration $database,
    ) {
    }

    /** @return array{image: string, envVars: array<string,string>, database: array{mode: string, urlVar: string, vars: array<string,string>}} */
    public function toArray(): array
    {
        return [
            'image' => $this->image,
            'envVars' => $this->envVars,
            'database' => $this->database->toArray(),
        ];
    }

    /** @param array{image: string, envVars?: array<string,string>, database?: array<string,mixed>} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['image'],
            $data['envVars'] ?? [],
            DatabaseDeclaration::fromArray($data['database'] ?? []),
        );
    }
}
