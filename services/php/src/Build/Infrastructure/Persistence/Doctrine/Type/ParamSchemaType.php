<?php

declare(strict_types=1);

namespace App\Build\Infrastructure\Persistence\Doctrine\Type;

use App\Build\Domain\ValueObject\ParamField;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/** Map<ParamFieldName, ParamField> de Template — persistido como JSON. */
final class ParamSchemaType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (!\is_array($value)) {
            return $value;
        }

        $encoded = array_map(static fn (ParamField $field): array => $field->toArray(), $value);

        return json_encode($encoded, \JSON_THROW_ON_ERROR);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if (null === $value) {
            return null;
        }

        $decoded = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);

        return array_map(static fn (array $field): ParamField => ParamField::fromArray($field), $decoded);
    }
}
