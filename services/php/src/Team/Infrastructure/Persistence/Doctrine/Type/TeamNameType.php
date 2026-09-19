<?php

declare(strict_types=1);

namespace App\Team\Infrastructure\Persistence\Doctrine\Type;

use App\Team\Domain\ValueObject\TeamName;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class TeamNameType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] = 150;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof TeamName ? $value->toString() : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?TeamName
    {
        return null === $value ? null : TeamName::fromString($value);
    }
}
