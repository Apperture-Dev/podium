<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Test double (solo `when@test`, ver security.yaml) — sin verificar firma:
 * el propio valor del Bearer token es el userId. Evita que los tests
 * funcionales necesiten un Keycloak real corriendo ni firmar JWTs de
 * verdad, mismo patrón que InMemoryPodiumManifestReader/TemplateResolver.
 */
final readonly class InMemoryAccessTokenHandler implements AccessTokenHandlerInterface
{
    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        if ('' === trim($accessToken)) {
            throw new BadCredentialsException('Empty test access token.');
        }

        return new UserBadge($accessToken, static fn (string $identifier): InMemoryUser => new InMemoryUser($identifier, null));
    }
}
