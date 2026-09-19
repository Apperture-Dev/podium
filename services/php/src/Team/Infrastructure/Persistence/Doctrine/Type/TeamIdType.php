<?php

declare(strict_types=1);

namespace App\Team\Infrastructure\Persistence\Doctrine\Type;

use App\Team\Domain\ValueObject\TeamId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TeamIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof TeamId ? $value->toString() : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TeamId
    {
        return null === $value ? null : TeamId::fromString($value);
    }
}
