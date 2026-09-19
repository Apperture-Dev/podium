<?php

declare(strict_types=1);

namespace Tests\Functional\Deploy;

use App\AppManager\Domain\Event\ApplicationDeployRequested;
use App\Deploy\Application\EventHandler\ApplicationDeployRequestedHandler;
use App\Deploy\Application\EventHandler\HealthCheckExhaustedHandler;
use App\Deploy\Application\EventHandler\HealthCheckSucceededHandler;
use App\Deploy\Application\Message\HealthCheckExhausted;
use App\Deploy\Application\Message\HealthCheckSucceeded;
use App\Deploy\Domain\DeployStatus;
use App\Deploy\Domain\Event\DeployAttemptRequested;
use App\Deploy\Domain\Event\DeployFailed;
use App\Deploy\Domain\Event\DeploySucceeded;
use App\Deploy\Domain\Port\DeployAttemptRepository;
use App\Deploy\Domain\ValueObject\DeployAttemptId;
use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\ProjectName;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class DeployAttemptLifecycleTest extends KernelTestCase
{
    private ApplicationDeployRequestedHandler $applicationDeployRequestedHandler;
    private HealthCheckSucceededHandler $healthCheckSucceededHandler;
    private HealthCheckExhaustedHandler $healthCheckExhaustedHandler;
    private DeployAttemptRepository $deployAttempts;
    private string $projectId;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();
        $this->applicationDeployRequestedHandler = $container->get(ApplicationDeployRequestedHandler::class);
        $this->healthCheckSucceededHandler = $container->get(HealthCheckSucceededHandler::class);
        $this->healthCheckExhaustedHandler = $container->get(HealthCheckExhaustedHandler::class);
        $this->deployAttempts = $container->get(DeployAttemptRepository::class);

        $projects = $container->get(ProjectRepository::class);
        $project = Project::register('https://github.com/team/repo', 'team-1', ProjectName::fromString('Test Project'));
        $projects->save($project);
        $this->projectId = $project->id()->toString();
    }

    public function testDeploySucceedsAndPublishesDeployAttemptRequestedThenDeploySucceeded(): void
    {
        ($this->applicationDeployRequestedHandler)(new ApplicationDeployRequested('backend', $this->projectId, 'v1', 'registry/backend:rev-1', ['API_URL' => 'https://x'], []));

        $requested = $this->envelopesOn('deploy_attempt_requested');
        self::assertCount(1, $requested);
        self::assertInstanceOf(DeployAttemptRequested::class, $requested[0]->getMessage());
        $deployAttemptId = $requested[0]->getMessage()->deployAttemptId;

        ($this->healthCheckSucceededHandler)(new HealthCheckSucceeded($deployAttemptId));

        $deployAttempt = $this->deployAttempts->get(DeployAttemptId::fromString($deployAttemptId));
        self::assertSame(DeployStatus::Succeeded, $deployAttempt->status());

        $succeeded = $this->envelopesOn('deploy_succeeded');
        self::assertCount(1, $succeeded);
        self::assertInstanceOf(DeploySucceeded::class, $succeeded[0]->getMessage());
    }

    public function testDeployFailsAndPublishesDeployFailed(): void
    {
        ($this->applicationDeployRequestedHandler)(new ApplicationDeployRequested('backend', $this->projectId, 'v1', 'registry/backend:rev-1', [], []));

        $requested = $this->envelopesOn('deploy_attempt_requested');
        $deployAttemptId = $requested[0]->getMessage()->deployAttemptId;

        ($this->healthCheckExhaustedHandler)(new HealthCheckExhausted($deployAttemptId, 'health check failed', 3));

        $deployAttempt = $this->deployAttempts->get(DeployAttemptId::fromString($deployAttemptId));
        self::assertSame(DeployStatus::Failed, $deployAttempt->status());
        self::assertSame(3, $deployAttempt->retryCount());

        $failed = $this->envelopesOn('deploy_failed_app_manager');
        self::assertCount(1, $failed);
        self::assertInstanceOf(DeployFailed::class, $failed[0]->getMessage());
        self::assertSame('health check failed', $failed[0]->getMessage()->errorMessage);
    }

    /** @return list<\Symfony\Component\Messenger\Envelope> */
    private function envelopesOn(string $transportName): array
    {
        $transport = self::getContainer()->get('messenger.transport.'.$transportName);
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport->getSent();
    }
}
