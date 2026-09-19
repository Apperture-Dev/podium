<?php

declare(strict_types=1);

namespace Tests\Functional\AppManager;

use App\AppManager\Application\EventHandler\ApplicationSourceChangedHandler;
use App\AppManager\Application\EventHandler\BuildFailedHandler;
use App\AppManager\Application\EventHandler\BuildSucceededHandler;
use App\AppManager\Application\EventHandler\DeployFailedHandler;
use App\AppManager\Application\EventHandler\DeploySucceededHandler;
use App\AppManager\Application\EventHandler\ServiceDiscoveredHandler;
use App\AppManager\Domain\ApplicationState;
use App\AppManager\Domain\Event\ApplicationBuildRequested;
use App\AppManager\Domain\Event\ApplicationDeployRequested;
use App\AppManager\Domain\Port\ApplicationRepository;
use App\Build\Domain\Event\BuildFailed;
use App\Build\Domain\Event\BuildSucceeded;
use App\Deploy\Domain\Event\DeployFailed;
use App\Deploy\Domain\Event\DeploySucceeded;
use App\Project\Domain\Event\ApplicationSourceChanged;
use App\Project\Domain\Event\ServiceDiscovered;
use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\ProjectName;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class ApplicationLifecycleTest extends KernelTestCase
{
    private ServiceDiscoveredHandler $serviceDiscoveredHandler;
    private ApplicationSourceChangedHandler $sourceChangedHandler;
    private BuildSucceededHandler $buildSucceededHandler;
    private BuildFailedHandler $buildFailedHandler;
    private DeploySucceededHandler $deploySucceededHandler;
    private DeployFailedHandler $deployFailedHandler;
    private ApplicationRepository $applications;
    private string $projectId;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();
        $this->serviceDiscoveredHandler = $container->get(ServiceDiscoveredHandler::class);
        $this->sourceChangedHandler = $container->get(ApplicationSourceChangedHandler::class);
        $this->buildSucceededHandler = $container->get(BuildSucceededHandler::class);
        $this->buildFailedHandler = $container->get(BuildFailedHandler::class);
        $this->deploySucceededHandler = $container->get(DeploySucceededHandler::class);
        $this->deployFailedHandler = $container->get(DeployFailedHandler::class);
        $this->applications = $container->get(ApplicationRepository::class);

        $projects = $container->get(ProjectRepository::class);
        $project = Project::register('https://github.com/team/repo', 'team-1', ProjectName::fromString('Test Project'));
        $projects->save($project);
        $this->projectId = $project->id()->toString();
    }

    public function testFullCycleFromDiscoveryToDeployed(): void
    {
        ($this->serviceDiscoveredHandler)(new ServiceDiscovered('backend', $this->projectId, 'php', 'symfony'));
        $application = $this->applications->findByProjectIdAndServiceName($this->projectId, 'backend');
        self::assertNotNull($application);
        self::assertSame(ApplicationState::Created, $application->state());

        ($this->sourceChangedHandler)(new ApplicationSourceChanged('backend', $this->projectId, 'rev-1', 'https://github.com/team/repo', 'github'));
        $application = $this->applications->get($application->id());
        self::assertSame(ApplicationState::Building, $application->state());

        $buildRequested = $this->envelopesOn('application_build_requested');
        self::assertCount(1, $buildRequested);
        self::assertInstanceOf(ApplicationBuildRequested::class, $buildRequested[0]->getMessage());

        ($this->buildSucceededHandler)(new BuildSucceeded('backend', $this->projectId, $application->version(), 'registry/backend:rev-1', [], []));
        $application = $this->applications->get($application->id());
        self::assertSame(ApplicationState::Deploying, $application->state());

        $deployRequested = $this->envelopesOn('application_deploy_requested');
        self::assertCount(1, $deployRequested);
        self::assertInstanceOf(ApplicationDeployRequested::class, $deployRequested[0]->getMessage());

        ($this->deploySucceededHandler)(new DeploySucceeded('backend', $this->projectId, $application->version()));
        $application = $this->applications->get($application->id());
        self::assertSame(ApplicationState::Deployed, $application->state());
    }

    public function testBuildFailurePath(): void
    {
        ($this->serviceDiscoveredHandler)(new ServiceDiscovered('frontend', $this->projectId, 'typescript', 'react'));
        ($this->sourceChangedHandler)(new ApplicationSourceChanged('frontend', $this->projectId, 'rev-1', 'https://github.com/team/repo', 'github'));

        ($this->buildFailedHandler)(new BuildFailed('frontend', $this->projectId, 'v1', 'compile error'));

        $application = $this->applications->findByProjectIdAndServiceName($this->projectId, 'frontend');
        self::assertSame(ApplicationState::BuildFailed, $application->state());
    }

    public function testDeployFailurePath(): void
    {
        ($this->serviceDiscoveredHandler)(new ServiceDiscovered('worker', $this->projectId, 'go', 'none'));
        ($this->sourceChangedHandler)(new ApplicationSourceChanged('worker', $this->projectId, 'rev-1', 'https://github.com/team/repo', 'github'));
        $application = $this->applications->findByProjectIdAndServiceName($this->projectId, 'worker');
        ($this->buildSucceededHandler)(new BuildSucceeded('worker', $this->projectId, $application->version(), 'registry/worker:rev-1', [], []));

        ($this->deployFailedHandler)(new DeployFailed('worker', $this->projectId, $application->version(), 'health check failed', null));

        $application = $this->applications->get($application->id());
        self::assertSame(ApplicationState::DeployFailed, $application->state());
    }

    /** @return list<\Symfony\Component\Messenger\Envelope> */
    private function envelopesOn(string $transportName): array
    {
        $transport = self::getContainer()->get('messenger.transport.'.$transportName);
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport->getSent();
    }
}
