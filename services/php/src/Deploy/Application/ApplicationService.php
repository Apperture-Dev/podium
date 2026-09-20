<?php

declare(strict_types=1);

namespace App\Deploy\Application;

use App\Deploy\Domain\DeployAttempt;
use App\Deploy\Domain\Port\DeployAttemptRepository;
use App\Deploy\Domain\Port\PodiumManifestReader;
use App\Deploy\Domain\ValueObject\DatabaseDeclaration;
use App\Deploy\Domain\ValueObject\DeployAttemptId;
use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\ValueObject\ProjectId;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ApplicationService
{
    public function __construct(
        private DeployAttemptRepository $deployAttempts,
        private ProjectRepository $projects,
        private PodiumManifestReader $manifestReader,
        private MessageBusInterface $eventBus,
    ) {
    }

    /**
     * Reacciona a `ApplicationDeployRequested` (publicado por App Manager).
     *
     * Lee del `podium.yaml` de la revisión construida lo que a Deploy le
     * concierne: la declaración de base de datos. No se la pasa Build.
     */
    public function requestDeploy(string $serviceName, string $projectId, string $version, string $image, int $port, string $commitId, string $repositoryUrl, string $provider): void
    {
        $project = $this->projects->get(ProjectId::fromString($projectId));
        $database = DatabaseDeclaration::fromManifest(
            $this->manifestReader->databaseBlockFor($repositoryUrl, $commitId, $serviceName),
        );

        $deployAttempt = DeployAttempt::request(
            $project->teamId(),
            $serviceName,
            $projectId,
            $project->hash()->toString(),
            $version,
            $image,
            $port,
            [],
            $database,
        );
        $events = $deployAttempt->releaseEvents();

        $this->deployAttempts->save($deployAttempt);
        $this->dispatchAll($events);
    }

    /** Reacciona a `HealthCheckSucceeded` (lanzador de ArgoCD, Go, diferido). */
    public function completeDeployAttempt(string $deployAttemptId): void
    {
        $deployAttempt = $this->deployAttempts->get(DeployAttemptId::fromString($deployAttemptId));

        $events = $deployAttempt->completeDeployAttempt();

        $this->deployAttempts->save($deployAttempt);
        $this->dispatchAll($events);
    }

    /** Reacciona a `HealthCheckExhausted` (lanzador de ArgoCD, Go, diferido). */
    public function failDeployAttempt(string $deployAttemptId, string $errorMessage, ?int $retryCount): void
    {
        $deployAttempt = $this->deployAttempts->get(DeployAttemptId::fromString($deployAttemptId));

        $events = $deployAttempt->failDeployAttempt($errorMessage, $retryCount);

        $this->deployAttempts->save($deployAttempt);
        $this->dispatchAll($events);
    }

    /** @param list<object> $events */
    private function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
