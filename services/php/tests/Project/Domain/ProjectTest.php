<?php

declare(strict_types=1);

namespace App\Tests\Project\Domain;

use App\Project\Domain\Event\ApplicationSourceChanged;
use App\Project\Domain\Event\ServiceDiscovered;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\DeclaredService;
use PHPUnit\Framework\TestCase;

final class ProjectTest extends TestCase
{
    public function testKnownServiceProducesApplicationSourceChanged(): void
    {
        $project = Project::register('https://github.com/team/repo', 'team-1');
        $project->processSourceChanged('rev-0', 'https://github.com/team/repo', 'github', [
            new DeclaredService('backend', 'php', 'symfony'),
        ]);

        $events = $project->processSourceChanged('rev-1', 'https://github.com/team/repo', 'github', [
            new DeclaredService('backend', 'php', 'symfony'),
        ]);

        self::assertCount(1, $events);
        self::assertInstanceOf(ApplicationSourceChanged::class, $events[0]);
        self::assertSame('backend', $events[0]->serviceName);
        self::assertSame(['backend'], $project->knownServiceNames());
    }

    public function testNewServiceProducesServiceDiscoveredAndBecomesKnown(): void
    {
        $project = Project::register('https://github.com/team/repo', 'team-1');

        $events = $project->processSourceChanged('rev-1', 'https://github.com/team/repo', 'github', [
            new DeclaredService('frontend', 'typescript', 'react'),
        ]);

        self::assertCount(1, $events);
        self::assertInstanceOf(ServiceDiscovered::class, $events[0]);
        self::assertSame('frontend', $events[0]->serviceName);
        self::assertSame(['frontend'], $project->knownServiceNames());
    }

    public function testMixedKnownAndNewServicesProduceOneEventEach(): void
    {
        $project = Project::register('https://github.com/team/repo', 'team-1');
        $project->processSourceChanged('rev-0', 'https://github.com/team/repo', 'github', [
            new DeclaredService('backend', 'php', 'symfony'),
        ]);

        $events = $project->processSourceChanged('rev-1', 'https://github.com/team/repo', 'github', [
            new DeclaredService('backend', 'php', 'symfony'),
            new DeclaredService('frontend', 'typescript', 'react'),
        ]);

        self::assertCount(2, $events);
        self::assertInstanceOf(ApplicationSourceChanged::class, $events[0]);
        self::assertInstanceOf(ServiceDiscovered::class, $events[1]);
        self::assertSame(['backend', 'frontend'], $project->knownServiceNames());
    }

    public function testEventsAreReleasedAfterEachCall(): void
    {
        $project = Project::register('https://github.com/team/repo', 'team-1');
        $project->processSourceChanged('rev-0', 'https://github.com/team/repo', 'github', [
            new DeclaredService('backend', 'php', 'symfony'),
        ]);

        $events = $project->processSourceChanged('rev-1', 'https://github.com/team/repo', 'github', []);

        self::assertSame([], $events);
    }
}
