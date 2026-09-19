<?php

declare(strict_types=1);

namespace App\Project\Application;

use App\Project\Domain\Port\PodiumManifestReader;
use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\ProjectId;
use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\ValueObject\TeamId;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ApplicationService
{
    public function __construct(
        private ProjectRepository $projects,
        private PodiumManifestReader $manifestReader,
        private MessageBusInterface $eventBus,
        private TeamRepository $teams,
    ) {
    }

    /**
     * El equipo da a Podium una repositoryUrl por primera vez. Valida que el
     * teamId exista — referencia entre BCs solo por id, la existencia la
     * comprueba el orquestador, nunca el dominio.
     */
    public function registerProject(string $repositoryUrl, string $teamId): string
    {
        $this->teams->get(TeamId::fromString($teamId));

        $project = Project::register($repositoryUrl, $teamId);
        $events = $project->releaseEvents();

        $this->projects->save($project);

        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }

        return $project->id()->toString();
    }

    public function processSourceChanged(
        string $projectId,
        string $revision,
        string $repositoryUrl,
        string $provider,
    ): void {
        $project = $this->projects->get(ProjectId::fromString($projectId));

        $declaredServices = $this->manifestReader->read($repositoryUrl, $revision);

        $events = $project->processSourceChanged($revision, $repositoryUrl, $provider, $declaredServices);

        $this->projects->save($project);

        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
