<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\GitHub;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Descarga y parsea el `podium.yaml` de un repo en una revisión concreta, vía
 * la API de Contents de GitHub — único proveedor soportado hoy (ver
 * appsource/discovery.md).
 *
 * Vive en Shared y no en un BC porque hay dos lectores reales del mismo
 * fichero, cada uno con su propio puerto de dominio y quedándose con lo suyo:
 * Project descubre los servicios declarados, Deploy lee el bloque de base de
 * datos del servicio que va a desplegar. Lo compartido es bajar el fichero,
 * no interpretarlo.
 */
final class PodiumManifestFetcher
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'GITHUB_TOKEN')]
        private readonly string $githubToken,
    ) {
    }

    /** @return array<string, mixed> el yaml completo, una clave por servicio */
    public function fetch(string $repositoryUrl, string $revision): array
    {
        $repo = GitHubRepositoryUrl::parse($repositoryUrl);

        $response = $this->httpClient->request(
            'GET',
            \sprintf('https://api.github.com/repos/%s/%s/contents/podium.yaml', $repo->owner, $repo->repo),
            [
                'query' => ['ref' => $revision],
                'headers' => [
                    'Authorization' => 'Bearer '.$this->githubToken,
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'podium-appsource-poller',
                ],
            ],
        );

        $statusCode = $response->getStatusCode();
        if (404 === $statusCode) {
            throw new RuntimeException(\sprintf('podium.yaml not found in %s/%s at revision %s.', $repo->owner, $repo->repo, $revision));
        }
        if ($statusCode >= 300) {
            throw new RuntimeException(\sprintf('GitHub Contents API returned %d for %s/%s.', $statusCode, $repo->owner, $repo->repo));
        }

        $payload = $response->toArray();
        $content = base64_decode(str_replace("\n", '', $payload['content'] ?? ''), true);
        if (false === $content) {
            throw new RuntimeException(\sprintf('podium.yaml content for %s/%s is not valid base64.', $repo->owner, $repo->repo));
        }

        $manifest = Yaml::parse($content);
        if (!\is_array($manifest)) {
            throw new RuntimeException(\sprintf('podium.yaml for %s/%s did not parse into a mapping.', $repo->owner, $repo->repo));
        }

        return $manifest;
    }
}
