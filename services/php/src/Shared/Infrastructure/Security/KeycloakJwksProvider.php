<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Cachea el JWKS del realm de Keycloak — evita una llamada de red por request. */
final readonly class KeycloakJwksProvider
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        #[Autowire('%env(KEYCLOAK_JWKS_URL)%')] private string $jwksUrl,
    ) {
    }

    /** @return array<string, mixed> */
    public function keySet(): array
    {
        return $this->cache->get('keycloak_jwks', function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            return $this->httpClient->request('GET', $this->jwksUrl)->toArray();
        });
    }
}
