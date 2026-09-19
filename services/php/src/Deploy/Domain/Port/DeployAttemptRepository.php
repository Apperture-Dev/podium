<?php

declare(strict_types=1);

namespace App\Deploy\Domain\Port;

use App\Deploy\Domain\DeployAttempt;
use App\Deploy\Domain\ValueObject\DeployAttemptId;

interface DeployAttemptRepository
{
    public function get(DeployAttemptId $id): DeployAttempt;

    public function save(DeployAttempt $deployAttempt): void;
}
