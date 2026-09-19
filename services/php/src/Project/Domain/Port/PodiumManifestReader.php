<?php

declare(strict_types=1);

namespace App\Project\Domain\Port;

use App\Project\Domain\ValueObject\DeclaredService;

interface PodiumManifestReader
{
    /** @return list<DeclaredService> */
    public function read(string $repositoryUrl, string $revision): array;
}
