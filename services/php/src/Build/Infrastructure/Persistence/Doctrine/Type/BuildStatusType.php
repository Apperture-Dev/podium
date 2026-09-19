<?php

declare(strict_types=1);

namespace App\Build\Infrastructure\Persistence\Doctrine\Type;

use App\Build\Domain\BuildStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class BuildStatusType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] = 20;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof BuildStatus ? $value->value : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BuildStatus
    {
        return null === $value ? null : BuildStatus::from($value);
    }
}
