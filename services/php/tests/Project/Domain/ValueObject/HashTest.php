<?php

declare(strict_types=1);

namespace App\Tests\Project\Domain\ValueObject;

use App\Project\Domain\ValueObject\Hash;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HashTest extends TestCase
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
