<?php

declare(strict_types=1);

namespace App\Deploy\Domain;

use App\Deploy\Domain\Event\DeployAttemptRequested;
use App\Deploy\Domain\Event\DeployFailed;
use App\Deploy\Domain\Event\DeploySucceeded;
use App\Deploy\Domain\ValueObject\DeployAttemptId;
use App\Deploy\Domain\ValueObject\DeployValues;

/**
 * Un intento concreto de despliegue para un servicio, paralelo a BuildJob
 * en Build. Nunca llama a la API de Kubernetes ni a ArgoCD: solo decide y
 * publica DeployAttemptRequested (los values para el chart genérico) para
 * que algo fuera de este BC (lanzador de ArgoCD, Go, diferido) haga el
 * kubectl apply real — ver deploy/model.md "Frontera de infraestructura".
 */
final class DeployAttempt
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly DeployAttemptId $id,
        private readonly string $teamId,
        private readonly string $serviceName,
        private readonly string $projectId,
        private readonly string $version,
        private readonly DeployValues $values,
        private DeployStatus $status,
        private ?string $errorMessage,
        private ?int $retryCount,
    ) {
    }

    /**
     * Reacciona a `ApplicationDeployRequested` (App Manager).
     *
     * @param array<string, string> $deployEnvVars
     * @param array<string, string> $databaseDeclaration
     */
    public static function request(
        string $teamId,
        string $serviceName,
        string $projectId,
        string $hash,
        string $version,
        string $image,
        array $deployEnvVars,
        array $databaseDeclaration,
    ): self {
        $deployAttempt = new self(
            DeployAttemptId::generate(),
            $teamId,
            $serviceName,
            $projectId,
            $version,
            new DeployValues($image, $deployEnvVars, $databaseDeclaration),
            DeployStatus::Pending,
            null,
            null,
        );

        $deployAttempt->record(new DeployAttemptRequested(
            $deployAttempt->id->toString(),
            $hash,
            $serviceName,
            $image,
            $deployEnvVars,
            $databaseDeclaration,
        ));

        return $deployAttempt;
    }

    public static function rehydrate(
        DeployAttemptId $id,
        string $teamId,
        string $serviceName,
        string $projectId,
        string $version,
        DeployValues $values,
        DeployStatus $status,
        ?string $errorMessage,
        ?int $retryCount,
    ): self {
        return new self($id, $teamId, $serviceName, $projectId, $version, $values, $status, $errorMessage, $retryCount);
    }

    /** Traduce la señal de infraestructura "ArgoCD confirma salud" (`HealthCheckSucceeded`). */
    public function completeDeployAttempt(): array
    {
        $this->status = DeployStatus::Succeeded;

        $this->record(new DeploySucceeded($this->serviceName, $this->projectId, $this->version));

        return $this->releaseEvents();
    }

    /**
     * Traduce la señal de infraestructura "reintentos agotados" (`HealthCheckExhausted`).
     * `retryCount` es informativo — la política de reintentos la decide
     * ArgoCD/el lanzador, nunca este dominio (ver deploy/model.md).
     */
    public function failDeployAttempt(string $errorMessage, ?int $retryCount): array
    {
        $this->status = DeployStatus::Failed;
        $this->errorMessage = $errorMessage;
        $this->retryCount = $retryCount;

        $this->record(new DeployFailed($this->serviceName, $this->projectId, $this->version, $errorMessage, $retryCount));

        return $this->releaseEvents();
    }

    public function id(): DeployAttemptId
    {
        return $this->id;
    }

    public function teamId(): string
    {
        return $this->teamId;
    }

    public function serviceName(): string
    {
        return $this->serviceName;
    }

    public function projectId(): string
    {
        return $this->projectId;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function values(): DeployValues
    {
        return $this->values;
    }

    public function status(): DeployStatus
    {
        return $this->status;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function retryCount(): ?int
    {
        return $this->retryCount;
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
