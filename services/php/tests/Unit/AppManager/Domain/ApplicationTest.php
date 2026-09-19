<?php

declare(strict_types=1);

namespace Tests\Unit\AppManager\Domain;

use App\AppManager\Domain\Application;
use App\AppManager\Domain\ApplicationState;
use App\AppManager\Domain\Event\ApplicationBuildRequested;
use App\AppManager\Domain\Event\ApplicationDeployRequested;
use App\AppManager\Domain\Event\ApplicationRegistered;
use Tests\Unit\UnitTestCase;

final class ApplicationTest extends UnitTestCase
{
    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->application = Application::register('backend', 'project-1', 'team-1', 'template-1');
        $this->application->releaseEvents();
    }

    public function testRegisterStartsInCreatedAndPublishesApplicationRegistered(): void
    {
        $application = Application::register('backend', 'project-1', 'team-1', 'template-1');

        $events = $application->releaseEvents();

        self::assertSame(ApplicationState::Created, $application->state());
        self::assertFalse($application->hasPendingSourceChange());
        self::assertCount(1, $events);
        self::assertInstanceOf(ApplicationRegistered::class, $events[0]);
        self::assertSame('backend', $events[0]->serviceName);
        self::assertCount(1, $application->historyLogs());
    }

    public function testSourceChangedFromCreatedEntersBuildingAndMintsVersion(): void
    {
        $versionBefore = $this->application->version();

        $events = $this->application->markSourceChanged('rev-1', 'https://github.com/team/repo', 'github');

        self::assertSame(ApplicationState::Building, $this->application->state());
        self::assertNotSame($versionBefore, $this->application->version());
        self::assertCount(1, $events);
        self::assertInstanceOf(ApplicationBuildRequested::class, $events[0]);
        self::assertSame('rev-1', $events[0]->revision);
    }

    public function testSourceChangedWhileBuildingCancelsAndRestarts(): void
    {
        $this->application->markSourceChanged('rev-1', 'https://github.com/team/repo', 'github');
        $versionAfterFirst = $this->application->version();

        $events = $this->application->markSourceChanged('rev-2', 'https://github.com/team/repo', 'github');

        self::assertSame(ApplicationState::Building, $this->application->state());
        self::assertNotSame($versionAfterFirst, $this->application->version());
        self::assertCount(1, $events);
        self::assertSame('rev-2', $events[0]->revision);
    }

    public function testSourceChangedWhileDeployingDoesNotInterruptAndMarksPending(): void
    {
        $this->application->markSourceChanged('rev-1', 'https://github.com/team/repo', 'github');
        $this->application->markBuildSucceeded('image:rev-1', [], []);
        self::assertSame(ApplicationState::Deploying, $this->application->state());
        $versionDuringDeploy = $this->application->version();

        $events = $this->application->markSourceChanged('rev-2', 'https://github.com/team/repo', 'github');

        self::assertSame(ApplicationState::Deploying, $this->application->state());
        self::assertSame($versionDuringDeploy, $this->application->version());
        self::assertTrue($this->application->hasPendingSourceChange());
        self::assertSame([], $events);
    }

    public function testBuildSucceededMovesToDeployingAndRequestsDeploy(): void
    {
        $this->application->markSourceChanged('rev-1', 'https://github.com/team/repo', 'github');

        $events = $this->application->markBuildSucceeded('image:rev-1', [], []);

        self::assertSame(ApplicationState::Deploying, $this->application->state());
        self::assertCount(1, $events);
        self::assertInstanceOf(ApplicationDeployRequested::class, $events[0]);
        self::assertSame('image:rev-1', $events[0]->image);
    }

    public function testBuildFailedMovesToBuildFailedWithoutPublishingAnEvent(): void
    {
        $this->application->markSourceChanged('rev-1', 'https://github.com/team/repo', 'github');

        $events = $this->application->markBuildFailed();

        self::assertSame(ApplicationState::BuildFailed, $this->application->state());
        self::assertSame([], $events);
    }

    public function testBuildFailedThenSourceChangedReEntersBuilding(): void
    {
        $this->application->markSourceChanged('rev-1', 'https://github.com/team/repo', 'github');
        $this->application->markBuildFailed();

        $events = $this->application->markSourceChanged('rev-2', 'https://github.com/team/repo', 'github');

        self::assertSame(ApplicationState::Building, $this->application->state());
        self::assertCount(1, $events);
    }

    public function testDeploySucceededMovesToDeployedAndClearsPendingFlag(): void
    {
        $this->application->markSourceChanged('rev-1', 'https://github.com/team/repo', 'github');
        $this->application->markBuildSucceeded('image:rev-1', [], []);
        $this->application->markSourceChanged('rev-2', 'https://github.com/team/repo', 'github'); // queda pendiente

        $events = $this->application->markDeploySucceeded();

        self::assertSame(ApplicationState::Deployed, $this->application->state());
        self::assertFalse($this->application->hasPendingSourceChange());
        self::assertSame([], $events);
    }

    public function testDeployFailedMovesToDeployFailedAndClearsPendingFlag(): void
    {
        $this->application->markSourceChanged('rev-1', 'https://github.com/team/repo', 'github');
        $this->application->markBuildSucceeded('image:rev-1', [], []);

        $events = $this->application->markDeployFailed();

        self::assertSame(ApplicationState::DeployFailed, $this->application->state());
        self::assertFalse($this->application->hasPendingSourceChange());
        self::assertSame([], $events);
    }

    public function testEveryMutationProducesAHistoryLogEntry(): void
    {
        // El registro ya deja un log propio (before == after == snapshot inicial).
        self::assertCount(1, $this->application->historyLogs());

        $this->application->markSourceChanged('rev-1', 'https://github.com/team/repo', 'github');

        self::assertCount(2, $this->application->historyLogs());
        $log = $this->application->historyLogs()[1];
        self::assertSame(ApplicationState::Created, $log->before()->state);
        self::assertSame(ApplicationState::Building, $log->after()->state);
    }
}
