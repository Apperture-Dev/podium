<?php

declare(strict_types=1);

namespace App\Project\Application;

use App\Project\Domain\Port\PodiumManifestReader;
use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\ProjectDTO;
use App\Project\Domain\ValueObject\ProjectId;
use App\Project\Domain\ValueObject\ProjectName;
use App\Shared\Domain\Exception\AccessDeniedException;
use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\ValueObject\TeamId;
use App\Team\Domain\ValueObject\UserId;
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
    public function registerProject(string $repositoryUrl, string $teamId, string $name): string
    {
        $this->teams->get(TeamId::fromString($teamId));

        $project = Project::register($repositoryUrl, $teamId, ProjectName::fromString($name));
        $events = $project->releaseEvents();

        $this->projects->save($project);

        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }

        return $project->id()->toString();
    }

    /**
     * Lista los Project de un Team — antes valida que el userId autenticado
     * sea miembro de ese Team (teamId ya está materializado en Project,
     * así que filtrar es directo; lo que no es directo es la autorización).
     *
     * @return list<ProjectDTO>
     */
    public function listProjectsForTeam(string $teamId, string $requestingUserId): array
    {
        $team = $this->teams->get(TeamId::fromString($teamId));

        if (!$team->hasMember(UserId::fromString($requestingUserId))) {
            throw new AccessDeniedException(\sprintf('User "%s" cannot access team "%s".', $requestingUserId, $teamId));
        }

        $projects = $this->projects->findByTeamId($teamId);

        return array_map(static fn (Project $project): ProjectDTO => $project->toDTO(), $projects);
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
