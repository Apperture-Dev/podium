<?php

declare(strict_types=1);

namespace App\AppManager\Infrastructure\Persistence\Doctrine\Type;

use App\AppManager\Domain\ApplicationState;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ApplicationStateType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        $column['length'] = 20;

        return $platform->getStringTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof ApplicationState ? $value->value : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ApplicationState
    {
        return null === $value ? null : ApplicationState::from($value);
    }
}
