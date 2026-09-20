<?php

declare(strict_types=1);

namespace Tests\Integration\Project;

use App\Project\Domain\ValueObject\DeclaredService;
use App\Project\Infrastructure\Manifest\GitHubPodiumManifestReader;
use App\Shared\Infrastructure\GitHub\PodiumManifestFetcher;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * MockHttpClient sustituye la red real (Symfony no llama a nada externo) —
 * clasificado como test de integración de un adaptador de infraestructura
 * (no de dominio puro), aunque no necesite el kernel para ejecutarse.
 */
final class GitHubPodiumManifestReaderTest extends TestCase
{
    // Base64 of:
    // app:
    //   lang: nodejs
    //   framework: nestjs
    // frontend:
    //   lang: nodejs
    //   framework: nextjs
    private const PODIUM_YAML_BASE64 = "YXBwOgogIGxhbmc6IG5vZGVqcwogIGZyYW1ld29yazogbmVzdGpzCmZyb250ZW5kOgogIGxhbmc6\nIG5vZGVqcwogIGZyYW1ld29yazogbmV4dGpzCg==";

    public function testReadParsesDeclaredServicesFromTheRealShapeOfTheGitHubContentsResponse(): void
    {
        $client = new MockHttpClient(function (string $method, string $url) {
            self::assertSame('GET', $method);
            self::assertStringStartsWith('https://api.github.com/repos/team-a/app/contents/podium.yaml', $url);
            self::assertStringContainsString('ref=abc123', $url);

            return new MockResponse(json_encode([
                'content' => self::PODIUM_YAML_BASE64,
                'encoding' => 'base64',
            ], \JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $reader = new GitHubPodiumManifestReader(new PodiumManifestFetcher($client, 'fake-token'));

        $declaredServices = $reader->read('https://github.com/team-a/app', 'abc123');

        self::assertCount(2, $declaredServices);
        self::assertContainsOnlyInstancesOf(DeclaredService::class, $declaredServices);

        $byName = [];
        foreach ($declaredServices as $service) {
            $byName[$service->serviceName] = $service;
        }
        self::assertSame('nodejs', $byName['app']->lang);
        self::assertSame('nestjs', $byName['app']->framework);
        self::assertSame('nodejs', $byName['frontend']->lang);
        self::assertSame('nextjs', $byName['frontend']->framework);
    }

    public function testReadThrowsWhenPodiumYamlDoesNotExist(): void
    {
        $client = new MockHttpClient(fn () => new MockResponse('', ['http_code' => 404]));
        $reader = new GitHubPodiumManifestReader(new PodiumManifestFetcher($client, 'fake-token'));

        $this->expectException(RuntimeException::class);

        $reader->read('https://github.com/team-a/app', 'abc123');
    }

    public function testReadThrowsForAnUnparseableRepositoryUrl(): void
    {
        $client = new MockHttpClient();
        $reader = new GitHubPodiumManifestReader(new PodiumManifestFetcher($client, 'fake-token'));

        $this->expectException(RuntimeException::class);

        $reader->read('not-a-url', 'abc123');
    }
}
