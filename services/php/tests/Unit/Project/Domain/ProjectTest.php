<?php

declare(strict_types=1);

namespace Tests\Unit\Project\Domain;

use App\Project\Domain\Event\ApplicationSourceChanged;
use App\Project\Domain\Event\ServiceDiscovered;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\DeclaredService;
use Tests\Unit\UnitTestCase;

final class ProjectTest extends UnitTestCase
{
    private Project $project;
    private DeclaredService $backend;
    private DeclaredService $frontend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::register('https://github.com/team/repo', 'team-1');
        $this->backend = new DeclaredService('backend', 'php', 'symfony');
        $this->frontend = new DeclaredService('frontend', 'typescript', 'react');
    }

    public function testKnownServiceProducesApplicationSourceChanged(): void
    {
        $this->project->processSourceChanged('rev-0', 'https://github.com/team/repo', 'github', [$this->backend]);

        $events = $this->project->processSourceChanged('rev-1', 'https://github.com/team/repo', 'github', [$this->backend]);

        self::assertCount(1, $events);
        self::assertInstanceOf(ApplicationSourceChanged::class, $events[0]);
        self::assertSame('backend', $events[0]->serviceName);
        self::assertSame(['backend'], $this->project->knownServiceNames());
    }

    public function testNewServiceProducesServiceDiscoveredAndBecomesKnown(): void
    {
        $events = $this->project->processSourceChanged('rev-1', 'https://github.com/team/repo', 'github', [$this->frontend]);

        self::assertCount(1, $events);
        self::assertInstanceOf(ServiceDiscovered::class, $events[0]);
        self::assertSame('frontend', $events[0]->serviceName);
        self::assertSame(['frontend'], $this->project->knownServiceNames());
    }

    public function testMixedKnownAndNewServicesProduceOneEventEach(): void
    {
        $this->project->processSourceChanged('rev-0', 'https://github.com/team/repo', 'github', [$this->backend]);

        $events = $this->project->processSourceChanged('rev-1', 'https://github.com/team/repo', 'github', [$this->backend, $this->frontend]);

        self::assertCount(2, $events);
        self::assertInstanceOf(ApplicationSourceChanged::class, $events[0]);
        self::assertInstanceOf(ServiceDiscovered::class, $events[1]);
        self::assertSame(['backend', 'frontend'], $this->project->knownServiceNames());
    }

    public function testEventsAreReleasedAfterEachCall(): void
    {
        $this->project->processSourceChanged('rev-0', 'https://github.com/team/repo', 'github', [$this->backend]);

        $events = $this->project->processSourceChanged('rev-1', 'https://github.com/team/repo', 'github', []);

        self::assertSame([], $events);
    }
}
