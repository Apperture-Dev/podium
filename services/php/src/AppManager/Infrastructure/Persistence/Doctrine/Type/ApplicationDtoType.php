<?php

declare(strict_types=1);

namespace App\AppManager\Infrastructure\Persistence\Doctrine\Type;

use App\AppManager\Domain\ValueObject\ApplicationDTO;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ApplicationDtoType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof ApplicationDTO ? json_encode($value->toArray(), \JSON_THROW_ON_ERROR) : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ApplicationDTO
    {
        return null === $value ? null : ApplicationDTO::fromArray(json_decode($value, true, flags: \JSON_THROW_ON_ERROR));
    }
}
