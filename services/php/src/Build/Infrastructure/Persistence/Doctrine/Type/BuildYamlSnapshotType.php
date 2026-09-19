<?php

declare(strict_types=1);

namespace App\Build\Infrastructure\Persistence\Doctrine\Type;

use App\Build\Domain\ValueObject\BuildYamlSnapshot;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/** Nullable — solo existe una vez que el BuildJob termina (Succeeded). */
final class BuildYamlSnapshotType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof BuildYamlSnapshot ? json_encode($value->toArray(), \JSON_THROW_ON_ERROR) : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?BuildYamlSnapshot
    {
        return null === $value ? null : BuildYamlSnapshot::fromArray(json_decode($value, true, flags: \JSON_THROW_ON_ERROR));
    }
}
