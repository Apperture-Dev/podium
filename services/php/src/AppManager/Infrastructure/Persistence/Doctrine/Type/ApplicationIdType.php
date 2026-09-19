<?php

declare(strict_types=1);

namespace App\AppManager\Infrastructure\Persistence\Doctrine\Type;

use App\AppManager\Domain\ValueObject\ApplicationId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class ApplicationIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof ApplicationId ? $value->toString() : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ApplicationId
    {
        return null === $value ? null : ApplicationId::fromString($value);
    }
}
