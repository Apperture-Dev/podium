<?php

declare(strict_types=1);

namespace App\AppSource\Domain\Port;

use App\AppSource\Domain\AppSource;
use App\AppSource\Domain\ValueObject\AppSourceId;

interface AppSourceRepository
{
    public function get(AppSourceId $id): AppSource;

    public function save(AppSource $appSource): void;

    /** @return list<AppSource> */
    public function findAll(): array;
}
