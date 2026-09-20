<?php

declare(strict_types=1);

namespace Tests\Integration\AppSource;

use App\AppSource\Infrastructure\GitHub\GitHubLatestCommitChecker;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GitHubLatestCommitCheckerTest extends TestCase
{
    public function testLatestRevisionReturnsTheCommitSha(): void
    {
        $client = new MockHttpClient(function (string $method, string $url) {
            self::assertSame('GET', $method);
            self::assertSame('https://api.github.com/repos/team-a/app/commits/main', $url);

            return new MockResponse(json_encode(['sha' => 'abc123def456'], \JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $checker = new GitHubLatestCommitChecker($client, 'fake-token');

        self::assertSame('abc123def456', $checker->latestRevision('https://github.com/team-a/app', 'github'));
    }

    public function testThrowsForAnUnsupportedProvider(): void
    {
        $checker = new GitHubLatestCommitChecker(new MockHttpClient(), 'fake-token');

        $this->expectException(RuntimeException::class);

        $checker->latestRevision('https://gitlab.com/team-a/app', 'gitlab');
    }

    public function testThrowsWhenGitHubReturnsAnError(): void
    {
        $client = new MockHttpClient(fn () => new MockResponse('', ['http_code' => 404]));
        $checker = new GitHubLatestCommitChecker($client, 'fake-token');

        $this->expectException(RuntimeException::class);

        $checker->latestRevision('https://github.com/team-a/app', 'github');
    }
}
