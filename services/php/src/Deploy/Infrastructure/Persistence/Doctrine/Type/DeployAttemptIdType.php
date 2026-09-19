<?php

declare(strict_types=1);

namespace App\Deploy\Infrastructure\Persistence\Doctrine\Type;

use App\Deploy\Domain\ValueObject\DeployAttemptId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class DeployAttemptIdType extends Type
{
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        return $value instanceof DeployAttemptId ? $value->toString() : $value;
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DeployAttemptId
    {
        return null === $value ? null : DeployAttemptId::fromString($value);
    }
}
