<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Manifest;

use App\Project\Domain\Port\PodiumManifestReader;
use RuntimeException;

/**
 * Adaptador real (clonar el repo, parsear podium.yaml) sin implementar todavía —
 * placeholder honesto para no fingir que la lectura del repo funciona hoy.
 */
final class NotImplementedPodiumManifestReader implements PodiumManifestReader
{
    public function read(string $repositoryUrl, string $revision): array
    {
        throw new RuntimeException('PodiumManifestReader has no real adapter yet — only a test double exists.');
    }
}
