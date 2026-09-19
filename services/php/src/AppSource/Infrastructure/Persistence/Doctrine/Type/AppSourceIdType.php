<?php

declare(strict_types=1);

namespace App\AppSource\Infrastructure\Persistence\Doctrine\Type;

use App\AppSource\Domain\ValueObject\AppSourceId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class AppSourceIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof AppSourceId ? $value->toString() : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AppSourceId
    {
        return null === $value ? null : AppSourceId::fromString($value);
    }
}
