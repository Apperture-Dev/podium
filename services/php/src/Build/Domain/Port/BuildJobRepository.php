<?php

declare(strict_types=1);

namespace App\Build\Domain\Port;

use App\Build\Domain\BuildJob;
use App\Build\Domain\ValueObject\BuildJobId;

interface BuildJobRepository
{
    public function get(BuildJobId $id): BuildJob;

    public function save(BuildJob $buildJob): void;
}
