<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Throwable;

/**
 * Verifica el Bearer JWT contra el JWKS de Keycloak (RS256) y expone el
 * claim `sub` como userId — nunca se modela un aggregate `User` propio (ver
 * context-map.md). Sin proveedor de usuarios real: InMemoryUser es solo un
 * envoltorio desechable para que Security tenga un UserInterface con el que
 * trabajar, roles/contraseña no aplican aquí.
 */
final readonly class KeycloakAccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private KeycloakJwksProvider $jwks)
    {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        try {
            $keys = JWK::parseKeySet($this->jwks->keySet());
            $decoded = JWT::decode($accessToken, $keys);
        } catch (Throwable) {
            throw new BadCredentialsException('Invalid or expired access token.');
        }

        $userId = $decoded->sub ?? null;
        if (!\is_string($userId) || '' === $userId) {
            throw new BadCredentialsException('Access token has no "sub" claim.');
        }

        return new UserBadge($userId, static fn (string $identifier): InMemoryUser => new InMemoryUser($identifier, null));
    }
}
