<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Manifest;

use App\Project\Domain\Port\PodiumManifestReader;
use App\Project\Domain\ValueObject\DeclaredService;

/**
 * Test double — usado solo en el entorno de test (ver config/services.yaml, when@test),
 * hasta que exista un adaptador real que clone el repo y parsee podium.yaml.
 */
final class InMemoryPodiumManifestReader implements PodiumManifestReader
{
    /** @var list<DeclaredService> */
    private array $declaredServices = [];

    /** @param list<DeclaredService> $declaredServices */
    public function willReturn(array $declaredServices): void
    {
        $this->declaredServices = $declaredServices;
    }

    public function read(string $repositoryUrl, string $revision): array
    {
        return $this->declaredServices;
    }
}
