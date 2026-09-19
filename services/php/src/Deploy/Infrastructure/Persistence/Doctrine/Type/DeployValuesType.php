<?php

declare(strict_types=1);

namespace App\Deploy\Infrastructure\Persistence\Doctrine\Type;

use App\Deploy\Domain\ValueObject\DeployValues;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class DeployValuesType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof DeployValues ? json_encode($value->toArray(), \JSON_THROW_ON_ERROR) : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DeployValues
    {
        return null === $value ? null : DeployValues::fromArray(json_decode($value, true, flags: \JSON_THROW_ON_ERROR));
    }
}
