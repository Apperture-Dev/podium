<?php

declare(strict_types=1);

namespace App\Build\Domain;

use App\Build\Domain\ValueObject\ParamField;
use App\Build\Domain\ValueObject\TemplateId;

/** Catálogo compartido por lenguaje/framework — nunca valores concretos de una app. */
final class Template
{
    private function __construct(
        private readonly TemplateId $id,
        private readonly string $language,
        private readonly string $framework,
        private readonly string $jobImage,
        /** @var array<string, ParamField> */
        private readonly array $paramSchema,
    ) {
    }

    /** @param array<string, ParamField> $paramSchema */
    public static function define(string $language, string $framework, string $jobImage, array $paramSchema): self
    {
        return new self(TemplateId::generate(), $language, $framework, $jobImage, $paramSchema);
    }

    /** @param array<string, ParamField> $paramSchema */
    public static function rehydrate(TemplateId $id, string $language, string $framework, string $jobImage, array $paramSchema): self
    {
        return new self($id, $language, $framework, $jobImage, $paramSchema);
    }

    public function id(): TemplateId
    {
        return $this->id;
    }

    public function language(): string
    {
        return $this->language;
    }

    public function framework(): string
    {
        return $this->framework;
    }

    public function jobImage(): string
    {
        return $this->jobImage;
    }

    /** @return array<string, ParamField> */
    public function paramSchema(): array
    {
        return $this->paramSchema;
    }
}
