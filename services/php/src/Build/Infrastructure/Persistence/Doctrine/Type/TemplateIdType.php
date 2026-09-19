<?php

declare(strict_types=1);

namespace App\Build\Infrastructure\Persistence\Doctrine\Type;

use App\Build\Domain\ValueObject\TemplateId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TemplateIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof TemplateId ? $value->toString() : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TemplateId
    {
        return null === $value ? null : TemplateId::fromString($value);
    }
}
