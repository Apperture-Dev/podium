<?php

declare(strict_types=1);

namespace App\Tests\Project\Domain\ValueObject;

use App\Project\Domain\ValueObject\Hash;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class HashTest extends TestCase
{
    public function test_generate_produces_eight_lowercase_alphanumeric_chars(): void
    {
        $hash = Hash::generate();

        self::assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $hash->toString());
    }

    public function test_rejects_invalid_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Hash::fromString('Not Valid!');
    }
}
