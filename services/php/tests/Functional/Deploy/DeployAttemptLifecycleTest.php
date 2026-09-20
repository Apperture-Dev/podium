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
use App\Deploy\Infrastructure\Manifest\InMemoryPodiumManifestReader;
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
    private InMemoryPodiumManifestReader $manifestReader;
    private string $projectId;
    private string $projectHash;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();
        $this->applicationDeployRequestedHandler = $container->get(ApplicationDeployRequestedHandler::class);
        $this->healthCheckSucceededHandler = $container->get(HealthCheckSucceededHandler::class);
        $this->healthCheckExhaustedHandler = $container->get(HealthCheckExhaustedHandler::class);
        $this->deployAttempts = $container->get(DeployAttemptRepository::class);
        $this->manifestReader = $container->get(InMemoryPodiumManifestReader::class);

        $projects = $container->get(ProjectRepository::class);
        $project = Project::register('https://github.com/team/repo', 'team-1', ProjectName::fromString('Test Project'));
        $projects->save($project);
        $this->projectId = $project->id()->toString();
        $this->projectHash = $project->hash()->toString();
    }

    public function testDeploySucceedsAndPublishesDeployAttemptRequestedThenDeploySucceeded(): void
    {
        ($this->applicationDeployRequestedHandler)(new ApplicationDeployRequested('backend', $this->projectId, 'v1', 'registry/backend:rev-1', 3000, 'rev-1', 'https://github.com/team/repo', 'github'));

        $requested = $this->envelopesOn('deploy_attempt_requested');
        self::assertCount(1, $requested);
        self::assertInstanceOf(DeployAttemptRequested::class, $requested[0]->getMessage());
        self::assertSame($this->projectHash, $requested[0]->getMessage()->hash);
        self::assertSame('backend', $requested[0]->getMessage()->serviceName);
        self::assertSame(3000, $requested[0]->getMessage()->port);
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
        ($this->applicationDeployRequestedHandler)(new ApplicationDeployRequested('backend', $this->projectId, 'v1', 'registry/backend:rev-1', 3000, 'rev-1', 'https://github.com/team/repo', 'github'));

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

    /**
     * El camino completo de la base de datos: Deploy lee el bloque del
     * podium.yaml de la revisión que se acaba de construir y publica la
     * declaración ya resuelta, con los nombres de env var que pidió el equipo
     * y las claves del Secret que genera CNPG.
     */
    public function testItResolvesTheDatabaseDeclaredInTheManifestOfTheBuiltRevision(): void
    {
        $this->manifestReader->willReturn(['enable' => true, 'URL' => '${DB_URL}']);

        ($this->applicationDeployRequestedHandler)(new ApplicationDeployRequested('backend', $this->projectId, 'v1', 'registry/backend:rev-1', 3000, 'rev-1', 'https://github.com/team/repo', 'github'));

        $requested = $this->envelopesOn('deploy_attempt_requested');
        self::assertCount(1, $requested);
        self::assertSame(
            ['mode' => 'url', 'urlVar' => 'DB_URL', 'vars' => []],
            $requested[0]->getMessage()->database,
        );
    }

    /** Sin bloque `database:` en el yaml, el chart recibe mode "none" y no monta nada. */
    public function testItAsksForNoDatabaseWhenTheManifestDeclaresNone(): void
    {
        $this->manifestReader->willReturn([]);

        ($this->applicationDeployRequestedHandler)(new ApplicationDeployRequested('backend', $this->projectId, 'v1', 'registry/backend:rev-1', 3000, 'rev-1', 'https://github.com/team/repo', 'github'));

        $requested = $this->envelopesOn('deploy_attempt_requested');
        self::assertSame('none', $requested[0]->getMessage()->database['mode']);
    }
}
