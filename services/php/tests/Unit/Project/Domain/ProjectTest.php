<?php

declare(strict_types=1);

namespace Tests\Unit\Project\Domain;

use App\Project\Domain\Event\ApplicationSourceChanged;
use App\Project\Domain\Event\ProjectRegistered;
use App\Project\Domain\Event\ServiceDiscovered;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\DeclaredService;
use App\Project\Domain\ValueObject\ProjectName;
use Tests\Unit\UnitTestCase;

final class ProjectTest extends UnitTestCase
{
    private Project $project;
    private DeclaredService $backend;
    private DeclaredService $frontend;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = Project::register('https://github.com/team/repo', 'team-1', ProjectName::fromString('Test Project'));
        $this->project->releaseEvents(); // estos tests son sobre processSourceChanged, no sobre el registro
        $this->backend = new DeclaredService('backend', 'php', 'symfony');
        $this->frontend = new DeclaredService('frontend', 'typescript', 'react');
    }

    public function testRegisterProducesProjectRegistered(): void
    {
        $project = Project::register('https://github.com/team/repo', 'team-1', ProjectName::fromString('Test Project'));

        $events = $project->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ProjectRegistered::class, $events[0]);
        self::assertSame($project->id()->toString(), $events[0]->projectId);
        self::assertSame('https://github.com/team/repo', $events[0]->repositoryUrl);
        self::assertSame('team-1', $events[0]->teamId);
        self::assertSame('Test Project', $project->name()->toString());
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

    /**
     * Sin esto, App Manager registra la Application pero nunca pide su
     * primer build: ApplicationSourceChanged (lo único que dispara
     * markSourceChanged) solo se emite para servicios ya conocidos, así que
     * un servicio nuevo se quedaba en Created para siempre. ServiceDiscovered
     * tiene que llevar los mismos datos de la revisión para que App Manager
     * pueda pedir el build inmediatamente al registrar.
     */
    public function testServiceDiscoveredCarriesTheRevisionSoTheFirstBuildCanBeRequested(): void
    {
        $events = $this->project->processSourceChanged('rev-1', 'https://github.com/team/repo', 'github', [$this->frontend]);

        self::assertSame('rev-1', $events[0]->revision);
        self::assertSame('https://github.com/team/repo', $events[0]->repositoryUrl);
        self::assertSame('github', $events[0]->provider);
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
