<?php

declare(strict_types=1);

namespace Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;

/**
 * Base class for HTTP functional tests.
 *
 * Functional tests should:
 * - Test a complete use case end-to-end, through its real entry point
 * - For endpoints with an HTTP surface: simulate the HTTP request and assert
 *   on the response, not on internal collaborators
 *
 * En los helpers de abajo, `$bearerToken` va directo al
 * `InMemoryAccessTokenHandler` de test (ver security.yaml) — sin verificar
 * firma, el propio valor es el userId.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected AbstractBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
    }

    protected function postJson(string $uri, array $payload, ?string $bearerToken = null): void
    {
        $this->client->request(
            'POST',
            $uri,
            server: $this->serverWith($bearerToken, ['CONTENT_TYPE' => 'application/json']),
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    protected function getJson(string $uri, ?string $bearerToken = null): void
    {
        $this->client->request('GET', $uri, server: $this->serverWith($bearerToken));
    }

    protected function jsonResponse(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /** @param array<string, string> $server */
    private function serverWith(?string $bearerToken, array $server = []): array
    {
        if (null !== $bearerToken) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$bearerToken;
        }

        return $server;
    }
}
