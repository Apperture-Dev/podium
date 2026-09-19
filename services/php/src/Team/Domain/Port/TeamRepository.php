<?php

declare(strict_types=1);

namespace App\Team\Domain\Port;

use App\Team\Domain\Team;
use App\Team\Domain\ValueObject\TeamId;
use App\Team\Domain\ValueObject\UserId;

interface TeamRepository
{
    public function get(TeamId $id): Team;

    /** @return list<Team> */
    public function findByUserId(UserId $userId): array;

    public function save(Team $team): void;
}
