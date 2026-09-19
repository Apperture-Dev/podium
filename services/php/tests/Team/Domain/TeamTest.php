<?php

declare(strict_types=1);

namespace App\Tests\Team\Domain;

use App\Team\Domain\Team;
use App\Team\Domain\ValueObject\TeamName;
use App\Team\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class TeamTest extends TestCase
{
    public function test_register_makes_creator_the_first_member(): void
    {
        $creator = UserId::fromString('user-1');

        $team = Team::register(TeamName::fromString('Podium Team'), $creator);

        self::assertCount(1, $team->members());
        self::assertTrue($creator->equals($team->members()[0]));
        self::assertSame('Podium Team', $team->name()->toString());
    }

    public function test_each_registration_gets_a_distinct_id(): void
    {
        $creator = UserId::fromString('user-1');

        $teamA = Team::register(TeamName::fromString('Team A'), $creator);
        $teamB = Team::register(TeamName::fromString('Team B'), $creator);

        self::assertFalse($teamA->id()->equals($teamB->id()));
    }
}
