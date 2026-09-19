<?php

declare(strict_types=1);

namespace Tests\Unit\Project\Domain\ValueObject;

use App\Project\Domain\ValueObject\Hash;
use InvalidArgumentException;
use Tests\Unit\UnitTestCase;

final class HashTest extends UnitTestCase
{
    public function testGenerateProducesEightLowercaseAlphanumericChars(): void
    {
        $hash = Hash::generate();

        self::assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $hash->toString());
    }

    public function testRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Hash::fromString('Not Valid!');
    }
}
