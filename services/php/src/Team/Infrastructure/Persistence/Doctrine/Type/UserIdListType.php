<?php

declare(strict_types=1);

namespace App\Team\Infrastructure\Persistence\Doctrine\Type;

use App\Team\Domain\ValueObject\UserId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/** Persiste `list<UserId>` como un array JSON de strings. */
final class UserIdListType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getJsonTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        return json_encode(array_map(static fn (UserId $userId): string => $userId->toString(), $value), \JSON_THROW_ON_ERROR);
    }

    /** @return list<UserId>|null */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        if (null === $value) {
            return null;
        }

        $decoded = is_string($value) ? json_decode($value, true, flags: \JSON_THROW_ON_ERROR) : $value;

        return array_map(static fn (string $userId): UserId => UserId::fromString($userId), $decoded);
    }
}
