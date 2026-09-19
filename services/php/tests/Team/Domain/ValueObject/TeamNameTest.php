<?php

declare(strict_types=1);

namespace App\Tests\Team\Domain\ValueObject;

use App\Team\Domain\ValueObject\TeamName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TeamNameTest extends TestCase
{
    public function test_accepts_a_name_up_to_150_characters(): void
    {
        $name = TeamName::fromString(str_repeat('a', 150));

        self::assertSame(150, mb_strlen($name->toString()));
    }

    public function test_rejects_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TeamName::fromString('   ');
    }

    public function test_rejects_name_longer_than_150_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TeamName::fromString(str_repeat('a', 151));
    }
}
