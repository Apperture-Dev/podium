<?php

declare(strict_types=1);

namespace Tests\Unit\Team\Domain;

use App\Team\Domain\Team;
use App\Team\Domain\ValueObject\TeamName;
use App\Team\Domain\ValueObject\UserId;
use Tests\Unit\UnitTestCase;

final class TeamTest extends UnitTestCase
{
    private UserId $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = UserId::fromString('user-1');
    }

    public function testRegisterMakesCreatorTheFirstMember(): void
    {
        $team = Team::register(TeamName::fromString('Podium Team'), $this->creator);

        self::assertCount(1, $team->members());
        self::assertTrue($this->creator->equals($team->members()[0]));
        self::assertSame('Podium Team', $team->name()->toString());
    }

    public function testEachRegistrationGetsADistinctId(): void
    {
        $teamA = Team::register(TeamName::fromString('Team A'), $this->creator);
        $teamB = Team::register(TeamName::fromString('Team B'), $this->creator);

        self::assertFalse($teamA->id()->equals($teamB->id()));
    }

    public function testHasMemberIsTrueForTheCreatorAndFalseForAnyoneElse(): void
    {
        $team = Team::register(TeamName::fromString('Podium Team'), $this->creator);

        self::assertTrue($team->hasMember($this->creator));
        self::assertFalse($team->hasMember(UserId::fromString('user-2')));
    }
}
