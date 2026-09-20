<?php

declare(strict_types=1);

namespace App\Deploy\Infrastructure\Manifest;

use App\Deploy\Domain\Port\PodiumManifestReader;
use App\Shared\Infrastructure\GitHub\PodiumManifestFetcher;

final class GitHubPodiumManifestReader implements PodiumManifestReader
{
    public function __construct(private readonly PodiumManifestFetcher $fetcher)
    {
    }

    public function databaseBlockFor(string $repositoryUrl, string $revision, string $serviceName): array
    {
        $manifest = $this->fetcher->fetch($repositoryUrl, $revision);
        $service = $manifest[$serviceName] ?? [];

        if (!\is_array($service) || !\is_array($service['database'] ?? null)) {
            return [];
        }

        return $service['database'];
    }
}
