<?php

declare(strict_types=1);

namespace App\AppSource\Infrastructure\GitHub;

use App\Shared\Infrastructure\GitHub\GitHubRepositoryUrl;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * NO es un puerto de dominio — "de dónde venga la revision (poller,
 * webhook) es infraestructura, no dominio" (appsource/model.md). El único
 * consumidor es PollAppSourcesCommand.
 *
 * Rama fija en "main": AppSource no guarda la rama del repositorio hoy —
 * primera versión, documentado como limitación conocida.
 */
final class GitHubLatestCommitChecker implements LatestCommitChecker
{
    private const DEFAULT_BRANCH = 'main';
    private const SUPPORTED_PROVIDER = 'github';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'GITHUB_TOKEN')]
        private readonly string $githubToken,
    ) {
    }

    public function latestRevision(string $repositoryUrl, string $provider): string
    {
        if (self::SUPPORTED_PROVIDER !== $provider) {
            throw new RuntimeException(\sprintf('GitHubLatestCommitChecker only supports the "%s" provider, got "%s".', self::SUPPORTED_PROVIDER, $provider));
        }

        $repo = GitHubRepositoryUrl::parse($repositoryUrl);

        $response = $this->httpClient->request(
            'GET',
            \sprintf('https://api.github.com/repos/%s/%s/commits/%s', $repo->owner, $repo->repo, self::DEFAULT_BRANCH),
            [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->githubToken,
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'podium-appsource-poller',
                ],
            ],
        );

        if ($response->getStatusCode() >= 300) {
            throw new RuntimeException(\sprintf('GitHub Commits API returned %d for %s/%s.', $response->getStatusCode(), $repo->owner, $repo->repo));
        }

        $payload = $response->toArray();
        $sha = $payload['sha'] ?? null;
        if (!\is_string($sha) || '' === $sha) {
            throw new RuntimeException(\sprintf('GitHub Commits API response for %s/%s had no "sha".', $repo->owner, $repo->repo));
        }

        return $sha;
    }
}
