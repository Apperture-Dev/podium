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
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected AbstractBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
    }

    protected function postJson(string $uri, array $payload): void
    {
        $this->client->request(
            'POST',
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    protected function jsonResponse(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }
}
