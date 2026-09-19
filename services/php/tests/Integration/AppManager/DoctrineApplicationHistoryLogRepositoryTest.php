<?php

declare(strict_types=1);

namespace Tests\Integration\AppManager;

use App\AppManager\Domain\Application;
use App\AppManager\Domain\ApplicationState;
use App\AppManager\Domain\Port\ApplicationHistoryLogRepository;
use App\AppManager\Domain\Port\ApplicationRepository;
use Tests\Integration\IntegrationTestCase;

final class DoctrineApplicationHistoryLogRepositoryTest extends IntegrationTestCase
{
    private ApplicationRepository $applications;
    private ApplicationHistoryLogRepository $historyLogs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applications = $this->getService(ApplicationRepository::class);
        $this->historyLogs = $this->getService(ApplicationHistoryLogRepository::class);
    }

    public function testEveryMutationIsPersistedAsAnOrderedHistoryLog(): void
    {
        $application = Application::register('backend', 'project-1', 'team-1', 'template-1', 'symfony');
        $application->markSourceChanged('rev-1', 'https://github.com/team/repo', 'github');
        $this->applications->save($application);
        $this->clearEntityManager();

        $logs = $this->historyLogs->findByApplicationId($application->id());

        self::assertCount(2, $logs);
        self::assertSame(ApplicationState::Created, $logs[0]->before()->state);
        self::assertSame(ApplicationState::Created, $logs[0]->after()->state);
        self::assertSame(ApplicationState::Created, $logs[1]->before()->state);
        self::assertSame(ApplicationState::Building, $logs[1]->after()->state);
    }

    public function testFindByApplicationIdReturnsEmptyWhenNoLogsExist(): void
    {
        $application = Application::register('backend', 'project-2', 'team-1', 'template-1', 'symfony');

        self::assertSame([], $this->historyLogs->findByApplicationId($application->id()));
    }
}
