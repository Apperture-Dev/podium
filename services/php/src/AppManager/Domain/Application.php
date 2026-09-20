<?php

declare(strict_types=1);

namespace App\AppManager\Domain;

use App\AppManager\Domain\Event\ApplicationBuildRequested;
use App\AppManager\Domain\Event\ApplicationDeployRequested;
use App\AppManager\Domain\Event\ApplicationRegistered;
use App\AppManager\Domain\ValueObject\ApplicationDTO;
use App\AppManager\Domain\ValueObject\ApplicationId;

final class Application
{
    /** @var list<object> */
    private array $recordedEvents = [];

    /** @var list<ApplicationHistoryLog> */
    private array $historyLogs = [];

    private function __construct(
        private readonly ApplicationId $id,
        private readonly string $serviceName,
        private readonly string $projectId,
        private readonly string $teamId,
        private readonly string $templateId,
        private readonly string $framework,
        private readonly \DateTimeImmutable $createdAt,
        private ApplicationState $state,
        private string $version,
        private bool $hasPendingSourceChange,
    ) {
    }

    /** Reacciona a `ServiceDiscovered` (publicado por Project). `framework` se copia tal cual del evento — misma materialización que ya se hace con `teamId`. */
    public static function register(string $serviceName, string $projectId, string $teamId, string $templateId, string $framework): self
    {
        $application = new self(
            ApplicationId::generate(),
            $serviceName,
            $projectId,
            $teamId,
            $templateId,
            $framework,
            new \DateTimeImmutable(),
            ApplicationState::Created,
            self::mintVersion(),
            false,
        );

        $application->record(new ApplicationRegistered(
            $application->id->toString(),
            $serviceName,
            $projectId,
            $teamId,
        ));
        $application->logMutation($application->toDTO());

        return $application;
    }

    public static function rehydrate(
        ApplicationId $id,
        string $serviceName,
        string $projectId,
        string $teamId,
        string $templateId,
        string $framework,
        \DateTimeImmutable $createdAt,
        ApplicationState $state,
        string $version,
        bool $hasPendingSourceChange,
    ): self {
        return new self($id, $serviceName, $projectId, $teamId, $templateId, $framework, $createdAt, $state, $version, $hasPendingSourceChange);
    }

    /**
     * Una sola acción, tres comportamientos según el estado actual:
     * - Deploying: no interrumpe, solo marca la revisión como pendiente.
     * - Cualquier otro estado (incluido Building, que cancela y reinicia): entra/permanece en Building con versión nueva.
     *
     * @return list<ApplicationBuildRequested>
     */
    public function markSourceChanged(string $revision, string $repositoryUrl, string $provider): array
    {
        $before = $this->toDTO();

        if (ApplicationState::Deploying === $this->state) {
            $this->hasPendingSourceChange = true;
            $this->logMutation($before);

            return $this->releaseEvents();
        }

        $this->state = ApplicationState::Building;
        $this->version = self::mintVersion();
        $this->hasPendingSourceChange = false;

        $this->record(new ApplicationBuildRequested(
            $this->serviceName,
            $this->projectId,
            $this->templateId,
            $this->version,
            $revision,
            $repositoryUrl,
            $provider,
        ));
        $this->logMutation($before);

        return $this->releaseEvents();
    }

    /**
     * Building → Built → Deploying, colapsado en una sola llamada (Built nunca queda en reposo).
     *
     * @param array<string, string> $deployEnvVars
     * @param array<string, string> $databaseDeclaration
     */
    public function markBuildSucceeded(string $image, int $port, array $deployEnvVars, array $databaseDeclaration): array
    {
        $before = $this->toDTO();
        $this->state = ApplicationState::Deploying;

        $this->record(new ApplicationDeployRequested($this->serviceName, $this->projectId, $this->version, $image, $port, $deployEnvVars, $databaseDeclaration));
        $this->logMutation($before);

        return $this->releaseEvents();
    }

    /** @return list<object> siempre vacía — App Manager no publica evento propio ante un build fallido. */
    public function markBuildFailed(): array
    {
        $before = $this->toDTO();
        $this->state = ApplicationState::BuildFailed;
        $this->logMutation($before);

        return $this->releaseEvents();
    }

    /** @return list<object> siempre vacía — App Manager no publica evento propio ante un deploy exitoso. */
    public function markDeploySucceeded(): array
    {
        $before = $this->toDTO();
        $this->state = ApplicationState::Deployed;
        $this->hasPendingSourceChange = false;
        $this->logMutation($before);

        return $this->releaseEvents();
    }

    /** @return list<object> siempre vacía — App Manager no publica evento propio ante un deploy fallido. */
    public function markDeployFailed(): array
    {
        $before = $this->toDTO();
        $this->state = ApplicationState::DeployFailed;
        $this->hasPendingSourceChange = false;
        $this->logMutation($before);

        return $this->releaseEvents();
    }

    public function toDTO(): ApplicationDTO
    {
        return new ApplicationDTO(
            $this->serviceName,
            $this->projectId,
            $this->teamId,
            $this->framework,
            $this->createdAt,
            $this->state,
            $this->version,
            $this->hasPendingSourceChange,
        );
    }

    public function id(): ApplicationId
    {
        return $this->id;
    }

    public function serviceName(): string
    {
        return $this->serviceName;
    }

    public function projectId(): string
    {
        return $this->projectId;
    }

    public function teamId(): string
    {
        return $this->teamId;
    }

    public function templateId(): string
    {
        return $this->templateId;
    }

    public function framework(): string
    {
        return $this->framework;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function state(): ApplicationState
    {
        return $this->state;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function hasPendingSourceChange(): bool
    {
        return $this->hasPendingSourceChange;
    }

    /** @return list<ApplicationHistoryLog> */
    public function historyLogs(): array
    {
        return $this->historyLogs;
    }

    private static function mintVersion(): string
    {
        return number_format(microtime(true), 6, '', '');
    }

    private function logMutation(ApplicationDTO $before): void
    {
        $this->historyLogs[] = ApplicationHistoryLog::record($this->id, $this->serviceName, $before, $this->toDTO());
    }

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /** @return list<object> */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
