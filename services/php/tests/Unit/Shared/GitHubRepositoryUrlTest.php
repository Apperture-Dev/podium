<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use App\Shared\Infrastructure\GitHub\GitHubRepositoryUrl;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GitHubRepositoryUrlTest extends TestCase
{
    public function testParsesOwnerAndRepoFromAPlainHttpsUrl(): void
    {
        $parsed = GitHubRepositoryUrl::parse('https://github.com/team-a/app');

        self::assertSame('team-a', $parsed->owner);
        self::assertSame('app', $parsed->repo);
    }

    public function testParsesOwnerAndRepoWithATrailingSlash(): void
    {
        $parsed = GitHubRepositoryUrl::parse('https://github.com/team-a/app/');

        self::assertSame('team-a', $parsed->owner);
        self::assertSame('app', $parsed->repo);
    }

    public function testStripsATrailingDotGitSuffix(): void
    {
        $parsed = GitHubRepositoryUrl::parse('https://github.com/team-a/app.git');

        self::assertSame('team-a', $parsed->owner);
        self::assertSame('app', $parsed->repo);
    }

    public function testThrowsForAnUrlThatIsNotOwnerSlashRepo(): void
    {
        $this->expectException(RuntimeException::class);

        GitHubRepositoryUrl::parse('https://github.com/team-a');
    }

    public function testThrowsForACompletelyInvalidUrl(): void
    {
        $this->expectException(RuntimeException::class);

        GitHubRepositoryUrl::parse('not-a-url');
    }
}
