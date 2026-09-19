<?php

declare(strict_types=1);

namespace Tests\Unit\Team\Domain\ValueObject;

use App\Team\Domain\ValueObject\TeamName;
use InvalidArgumentException;
use Tests\Unit\UnitTestCase;

final class TeamNameTest extends UnitTestCase
{
    public function testAcceptsANameUpTo150Characters(): void
    {
        $name = TeamName::fromString(str_repeat('a', 150));

        self::assertSame(150, mb_strlen($name->toString()));
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TeamName::fromString('   ');
    }

    public function testRejectsNameLongerThan150Characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TeamName::fromString(str_repeat('a', 151));
    }
}
