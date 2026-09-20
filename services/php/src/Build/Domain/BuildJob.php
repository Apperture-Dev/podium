<?php

declare(strict_types=1);

namespace App\Build\Domain;

use App\Build\Domain\Event\BuildFailed;
use App\Build\Domain\Event\BuildJobRequested;
use App\Build\Domain\Event\BuildSucceeded;
use App\Build\Domain\ValueObject\BuildJobId;
use App\Build\Domain\ValueObject\BuildYamlSnapshot;

/**
 * Un intento concreto de build para un servicio dentro de un Project. Sin
 * vida propia entre intentos — cada ApplicationBuildRequested crea uno
 * nuevo. Nunca clona el repo ni ejecuta nada: solo decide y publica
 * BuildJobRequested (imagen, comando, env vars) para que algo fuera de este
 * BC (lanzador Kubernetes, Go, diferido) cree el Job real — ver
 * build/model.md "Frontera de infraestructura".
 */
final class BuildJob
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly BuildJobId $id,
        private readonly string $teamId,
        private readonly string $serviceName,
        private readonly string $projectId,
        private readonly string $templateId,
        private readonly string $version,
        private readonly string $commitId,
        private readonly string $repositoryUrl,
        private readonly string $provider,
        private BuildStatus $status,
        private ?string $image,
        private ?BuildYamlSnapshot $yamlSnapshot,
        private ?string $errorMessage,
    ) {
    }

    /** Reacciona a `ApplicationBuildRequested` (App Manager), ya con el jobImage del Template resuelto. */
    public static function request(
        string $teamId,
        string $serviceName,
        string $projectId,
        string $templateId,
        string $jobImage,
        string $version,
        string $commitId,
        string $repositoryUrl,
        string $provider,
    ): self {
        $buildJob = new self(
            BuildJobId::generate(),
            $teamId,
            $serviceName,
            $projectId,
            $templateId,
            $version,
            $commitId,
            $repositoryUrl,
            $provider,
            BuildStatus::Pending,
            null,
            null,
            null,
        );

        $buildJob->record(new BuildJobRequested(
            $buildJob->id->toString(),
            $jobImage,
            [], // comando: siempre vacío hoy — convención de Template, no campo configurable (MVP)
            [
                'SERVICE_NAME' => $serviceName,
                'PROJECT_ID' => $projectId,
                'TEMPLATE_ID' => $templateId,
                'VERSION' => $version,
                'COMMIT_ID' => $commitId,
                'REPOSITORY_URL' => $repositoryUrl,
                'PROVIDER' => $provider,
                'BUILD_JOB_ID' => $buildJob->id->toString(),
            ],
        ));

        return $buildJob;
    }

    public static function rehydrate(
        BuildJobId $id,
        string $teamId,
        string $serviceName,
        string $projectId,
        string $templateId,
        string $version,
        string $commitId,
        string $repositoryUrl,
        string $provider,
        BuildStatus $status,
        ?string $image,
        ?BuildYamlSnapshot $yamlSnapshot,
        ?string $errorMessage,
    ): self {
        return new self($id, $teamId, $serviceName, $projectId, $templateId, $version, $commitId, $repositoryUrl, $provider, $status, $image, $yamlSnapshot, $errorMessage);
    }

    /**
     * Traduce la señal de infraestructura "el Job construyó bien" — imagen
     * resultante y el yaml que el propio Job leyó y validó.
     *
     * @param array<string, string> $buildEnvVars
     *
     * @return list<object>
     */
    public function completeBuildJob(string $image, int $port, array $buildEnvVars): array
    {
        $this->status = BuildStatus::Succeeded;
        $this->image = $image;
        $this->yamlSnapshot = new BuildYamlSnapshot($buildEnvVars);

        $this->record(new BuildSucceeded(
            $this->serviceName,
            $this->projectId,
            $this->version,
            $image,
            $port,
            $this->commitId,
            $this->repositoryUrl,
            $this->provider,
        ));

        return $this->releaseEvents();
    }

    /** Traduce un fallo de infraestructura, o un yaml inválido contra el paramSchema de Template — misma categoría, decididos por el propio Job. */
    public function failBuildJob(string $errorMessage): array
    {
        $this->status = BuildStatus::Failed;
        $this->errorMessage = $errorMessage;

        $this->record(new BuildFailed($this->serviceName, $this->projectId, $this->version, $errorMessage));

        return $this->releaseEvents();
    }

    public function id(): BuildJobId
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

    public function templateId(): string
    {
        return $this->templateId;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function commitId(): string
    {
        return $this->commitId;
    }

    public function repositoryUrl(): string
    {
        return $this->repositoryUrl;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function status(): BuildStatus
    {
        return $this->status;
    }

    public function image(): ?string
    {
        return $this->image;
    }

    public function yamlSnapshot(): ?BuildYamlSnapshot
    {
        return $this->yamlSnapshot;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
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
