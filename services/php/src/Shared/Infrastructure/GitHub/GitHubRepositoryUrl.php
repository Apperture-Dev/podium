<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\GitHub;

use RuntimeException;

/**
 * Parsea owner/repo de una URL de repositorio de GitHub — compartido entre
 * los adaptadores de Project (GitHubPodiumManifestReader) y AppSource
 * (GitHubLatestCommitChecker), que necesitan exactamente lo mismo.
 */
final readonly class GitHubRepositoryUrl
{
    private function __construct(
        public string $owner,
        public string $repo,
    ) {
    }

    public static function parse(string $repositoryUrl): self
    {
        $path = trim((string) parse_url($repositoryUrl, \PHP_URL_PATH), '/');
        $parts = explode('/', $path);
        if (2 !== \count($parts) || '' === $parts[0] || '' === $parts[1]) {
            throw new RuntimeException(\sprintf('Cannot parse owner/repo from repository URL "%s".', $repositoryUrl));
        }

        return new self($parts[0], $parts[1]);
    }
}
