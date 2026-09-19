<?php

declare(strict_types=1);

namespace App\AppManager\Application;

use App\AppManager\Domain\Application;
use App\AppManager\Domain\Port\ApplicationRepository;
use App\AppManager\Domain\Port\TemplateResolver;
use App\AppManager\Domain\ValueObject\ApplicationDTO;
use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\ProjectId;
use App\Shared\Domain\Exception\AccessDeniedException;
use App\Team\Domain\Port\TeamRepository;
use App\Team\Domain\ValueObject\TeamId;
use App\Team\Domain\ValueObject\UserId;
use RuntimeException;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ApplicationService
{
    public function __construct(
        private ApplicationRepository $applications,
        private ProjectRepository $projects,
        private TeamRepository $teams,
        private TemplateResolver $templateResolver,
        private MessageBusInterface $eventBus,
    ) {
    }

    /** Reacciona a `ServiceDiscovered` (publicado por Project). */
    public function registerApplication(string $serviceName, string $projectId, string $lang, string $framework): string
    {
        $project = $this->projects->get(ProjectId::fromString($projectId));
        $templateId = $this->templateResolver->resolve($lang, $framework);

        $application = Application::register($serviceName, $projectId, $project->teamId(), $templateId);
        $events = $application->releaseEvents();

        $this->applications->save($application);
        $this->dispatchAll($events);

        return $application->id()->toString();
    }

    /** Reacciona a `ApplicationSourceChanged` (publicado por Project). */
    public function markSourceChanged(string $projectId, string $serviceName, string $revision, string $repositoryUrl, string $provider): void
    {
        $application = $this->getByProjectIdAndServiceName($projectId, $serviceName);

        $events = $application->markSourceChanged($revision, $repositoryUrl, $provider);

        $this->applications->save($application);
        $this->dispatchAll($events);
    }

    /**
     * Reacciona a `BuildSucceeded` (publicado por Build).
     *
     * @param array<string, string> $deployEnvVars
     * @param array<string, string> $databaseDeclaration
     */
    public function markBuildSucceeded(string $projectId, string $serviceName, string $image, array $deployEnvVars, array $databaseDeclaration): void
    {
        $application = $this->getByProjectIdAndServiceName($projectId, $serviceName);

        $events = $application->markBuildSucceeded($image, $deployEnvVars, $databaseDeclaration);

        $this->applications->save($application);
        $this->dispatchAll($events);
    }

    /** Reacciona a `BuildFailed` (publicado por Build, Go). */
    public function markBuildFailed(string $projectId, string $serviceName): void
    {
        $application = $this->getByProjectIdAndServiceName($projectId, $serviceName);

        $application->markBuildFailed();

        $this->applications->save($application);
    }

    /** Reacciona a `DeploySucceeded` (publicado por Deploy, Go). */
    public function markDeploySucceeded(string $projectId, string $serviceName): void
    {
        $application = $this->getByProjectIdAndServiceName($projectId, $serviceName);

        $application->markDeploySucceeded();

        $this->applications->save($application);

        // Si quedó una revisión pendiente de cuando se disparó durante el
        // deploy, falta resolverla contra AppSource ("cuál es la última") —
        // capacidad de query entre BCs que no existe todavía. Ver
        // app-manager/discovery.md: nota no bloqueante sobre este punto.
    }

    /** Reacciona a `DeployFailed` (publicado por Deploy, Go). */
    public function markDeployFailed(string $projectId, string $serviceName): void
    {
        $application = $this->getByProjectIdAndServiceName($projectId, $serviceName);

        $application->markDeployFailed();

        $this->applications->save($application);
    }

    /**
     * Lista las Application de un Project dentro de un Team — antes valida
     * que el projectId pertenezca de verdad a ese teamId (si no, 404: esa
     * URL no existe) y que el userId autenticado sea miembro del Team real
     * (Application.teamId ya está materializado, pero la autorización se
     * resuelve contra el Team real, no contra la copia).
     *
     * @return list<ApplicationDTO>
     */
    public function listApplicationsForProject(string $teamId, string $projectId, string $requestingUserId): array
    {
        $project = $this->requireProjectInTeam($teamId, $projectId);
        $this->requireMember($project->teamId(), $requestingUserId);

        $applications = $this->applications->findByProjectId($projectId);

        return array_map(static fn (Application $application): ApplicationDTO => $application->toDTO(), $applications);
    }

    /** Misma autorización que `listApplicationsForProject`, para una única Application por `serviceName`. */
    public function getApplication(string $teamId, string $projectId, string $serviceName, string $requestingUserId): ApplicationDTO
    {
        $project = $this->requireProjectInTeam($teamId, $projectId);
        $this->requireMember($project->teamId(), $requestingUserId);

        $application = $this->applications->findByProjectIdAndServiceName($projectId, $serviceName)
            ?? throw new RuntimeException(\sprintf('Application "%s" not found for project "%s".', $serviceName, $projectId));

        return $application->toDTO();
    }

    private function requireProjectInTeam(string $teamId, string $projectId): Project
    {
        $project = $this->projects->get(ProjectId::fromString($projectId));

        if ($project->teamId() !== $teamId) {
            throw new RuntimeException(\sprintf('Project "%s" not found for team "%s".', $projectId, $teamId));
        }

        return $project;
    }

    private function requireMember(string $teamId, string $requestingUserId): void
    {
        $team = $this->teams->get(TeamId::fromString($teamId));

        if (!$team->hasMember(UserId::fromString($requestingUserId))) {
            throw new AccessDeniedException(\sprintf('User "%s" cannot access team "%s".', $requestingUserId, $teamId));
        }
    }

    private function getByProjectIdAndServiceName(string $projectId, string $serviceName): Application
    {
        return $this->applications->findByProjectIdAndServiceName($projectId, $serviceName)
            ?? throw new RuntimeException(\sprintf('Application "%s" not found for project "%s".', $serviceName, $projectId));
    }

    /** @param list<object> $events */
    private function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
