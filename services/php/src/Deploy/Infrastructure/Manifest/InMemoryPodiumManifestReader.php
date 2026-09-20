<?php

declare(strict_types=1);

namespace App\Deploy\Infrastructure\Manifest;

use App\Deploy\Domain\Port\PodiumManifestReader;

/** Test double — solo en el entorno de test (ver config/services.yaml, when@test). */
final class InMemoryPodiumManifestReader implements PodiumManifestReader
{
    /** @var array<string, mixed> */
    private array $databaseBlock = [];

    /** @param array<string, mixed> $databaseBlock */
    public function willReturn(array $databaseBlock): void
    {
        $this->databaseBlock = $databaseBlock;
    }

    public function databaseBlockFor(string $repositoryUrl, string $revision, string $serviceName): array
    {
        return $this->databaseBlock;
    }
}
