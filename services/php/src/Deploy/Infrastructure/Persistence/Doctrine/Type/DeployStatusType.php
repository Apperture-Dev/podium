<?php

declare(strict_types=1);

namespace App\Deploy\Infrastructure\Persistence\Doctrine\Type;

use App\Deploy\Domain\DeployStatus;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class DeployStatusType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] = 20;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof DeployStatus ? $value->value : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DeployStatus
    {
        return null === $value ? null : DeployStatus::from($value);
    }
}
