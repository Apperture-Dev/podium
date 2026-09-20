<?php

declare(strict_types=1);

namespace App\AppSource\Infrastructure\GitHub;

/**
 * Test double — usado solo en el entorno de test (ver config/services.yaml,
 * when@test), mismo patrón que InMemoryPodiumManifestReader.
 */
final class InMemoryLatestCommitChecker implements LatestCommitChecker
{
    /** @var array<string, string> */
    private array $revisionsByRepositoryUrl = [];

    public function willReturn(string $repositoryUrl, string $revision): void
    {
        $this->revisionsByRepositoryUrl[$repositoryUrl] = $revision;
    }

    public function latestRevision(string $repositoryUrl, string $provider): string
    {
        return $this->revisionsByRepositoryUrl[$repositoryUrl] ?? '';
    }
}
