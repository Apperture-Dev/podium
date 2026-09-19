<?php

declare(strict_types=1);

namespace App\Tests\Team\Domain;

use App\Team\Domain\Team;
use App\Team\Domain\ValueObject\TeamName;
use App\Team\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class TeamTest extends TestCase
{
    public function testRegisterMakesCreatorTheFirstMember(): void
    {
        $creator = UserId::fromString('user-1');

        $team = Team::register(TeamName::fromString('Podium Team'), $creator);

        self::assertCount(1, $team->members());
        self::assertTrue($creator->equals($team->members()[0]));
        self::assertSame('Podium Team', $team->name()->toString());
    }

    public function testEachRegistrationGetsADistinctId(): void
    {
        $creator = UserId::fromString('user-1');

        $teamA = Team::register(TeamName::fromString('Team A'), $creator);
        $teamB = Team::register(TeamName::fromString('Team B'), $creator);

        self::assertFalse($teamA->id()->equals($teamB->id()));
    }
}
