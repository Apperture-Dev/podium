<?php

declare(strict_types=1);

namespace App\Build\Domain\ValueObject;

/** Un campo del paramSchema de Template: tipo + obligatoriedad + restricción de forma (lo que impide inyección en un campo libre). */
final readonly class ParamField
{
    public function __construct(
        public string $type,
        public bool $required,
        public string $shapeConstraint,
    ) {
    }

    /** @return array{type: string, required: bool, shapeConstraint: string} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'required' => $this->required,
            'shapeConstraint' => $this->shapeConstraint,
        ];
    }

    /** @param array{type: string, required: bool, shapeConstraint: string} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['type'], $data['required'], $data['shapeConstraint']);
    }
}
