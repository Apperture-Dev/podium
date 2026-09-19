<?php

declare(strict_types=1);

namespace App\Team\Domain\Port;

use App\Team\Domain\Team;
use App\Team\Domain\ValueObject\TeamId;

interface TeamRepository
{
    public function get(TeamId $id): Team;

    public function save(Team $team): void;
}
