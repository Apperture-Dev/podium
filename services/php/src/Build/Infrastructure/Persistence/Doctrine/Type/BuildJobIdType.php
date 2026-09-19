<?php

declare(strict_types=1);

namespace App\Build\Infrastructure\Persistence\Doctrine\Type;

use App\Build\Domain\ValueObject\BuildJobId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class BuildJobIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof BuildJobId ? $value->toString() : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BuildJobId
    {
        return null === $value ? null : BuildJobId::fromString($value);
    }
}
