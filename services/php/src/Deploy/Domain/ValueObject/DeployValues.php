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
    public function __construct(
        public string $image,
        /** @var array<string, string> */
        public array $envVars,
        /** @var array<string, string> */
        public array $databaseDeclaration,
    ) {
    }

    /** @return array{image: string, envVars: array<string,string>, databaseDeclaration: array<string,string>} */
    public function toArray(): array
    {
        return [
            'image' => $this->image,
            'envVars' => $this->envVars,
            'databaseDeclaration' => $this->databaseDeclaration,
        ];
    }

    /** @param array{image: string, envVars: array<string,string>, databaseDeclaration: array<string,string>} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['image'], $data['envVars'], $data['databaseDeclaration']);
    }
}
