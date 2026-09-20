<?php

declare(strict_types=1);

namespace App\Project\Infrastructure\Manifest;

use App\Project\Domain\Port\PodiumManifestReader;
use App\Project\Domain\ValueObject\DeclaredService;
use App\Shared\Infrastructure\GitHub\PodiumManifestFetcher;

/**
 * Adaptador real de PodiumManifestReader: se queda con la lista de servicios
 * declarados. Bajar y parsear el fichero es de PodiumManifestFetcher, que
 * comparte con el lector de Deploy.
 */
final class GitHubPodiumManifestReader implements PodiumManifestReader
{
    public function __construct(private readonly PodiumManifestFetcher $fetcher)
    {
    }

    public function read(string $repositoryUrl, string $revision): array
    {
        $manifest = $this->fetcher->fetch($repositoryUrl, $revision);

        $declaredServices = [];
        foreach ($manifest as $serviceName => $definition) {
            $declaredServices[] = new DeclaredService(
                (string) $serviceName,
                (string) ($definition['lang'] ?? ''),
                (string) ($definition['framework'] ?? ''),
            );
        }

        return $declaredServices;
    }
}
