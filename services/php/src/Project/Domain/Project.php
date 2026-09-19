<?php

declare(strict_types=1);

namespace App\Project\Domain;

use App\Project\Domain\Event\ApplicationSourceChanged;
use App\Project\Domain\Event\ProjectRegistered;
use App\Project\Domain\Event\ServiceDiscovered;
use App\Project\Domain\ValueObject\DeclaredService;
use App\Project\Domain\ValueObject\Hash;
use App\Project\Domain\ValueObject\ProjectDTO;
use App\Project\Domain\ValueObject\ProjectId;
use App\Project\Domain\ValueObject\ProjectName;

final class Project
{
    /** @var list<string> */
    private array $knownServiceNames = [];

    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly ProjectId $id,
        private readonly Hash $hash,
        private readonly ProjectName $name,
        private readonly string $teamId,
        private readonly string $repositoryUrl,
    ) {
    }

    public static function register(string $repositoryUrl, string $teamId, ProjectName $name): self
    {
        $project = new self(ProjectId::generate(), Hash::generate(), $name, $teamId, $repositoryUrl);
        $project->record(new ProjectRegistered($project->id->toString(), $repositoryUrl, $teamId));

        return $project;
    }

    /** @param list<string> $knownServiceNames */
    public static function rehydrate(
        ProjectId $id,
        Hash $hash,
        ProjectName $name,
        string $teamId,
        string $repositoryUrl,
        array $knownServiceNames,
    ): self {
        $project = new self($id, $hash, $name, $teamId, $repositoryUrl);
        $project->knownServiceNames = $knownServiceNames;

        return $project;
    }

    /**
     * @param list<DeclaredService> $declaredServices
     *
     * @return list<ApplicationSourceChanged|ServiceDiscovered>
     */
    public function processSourceChanged(
        string $revision,
        string $repositoryUrl,
        string $provider,
        array $declaredServices,
    ): array {
        foreach ($declaredServices as $service) {
            if (\in_array($service->serviceName, $this->knownServiceNames, true)) {
                $this->record(new ApplicationSourceChanged(
                    $service->serviceName,
                    $this->id->toString(),
                    $revision,
                    $repositoryUrl,
                    $provider,
                ));

                continue;
            }

            $this->knownServiceNames[] = $service->serviceName;
            $this->record(new ServiceDiscovered(
                $service->serviceName,
                $this->id->toString(),
                $service->lang,
                $service->framework,
            ));
        }

        return $this->releaseEvents();
    }

    public function id(): ProjectId
    {
        return $this->id;
    }

    public function hash(): Hash
    {
        return $this->hash;
    }

    public function name(): ProjectName
    {
        return $this->name;
    }

    public function teamId(): string
    {
        return $this->teamId;
    }

    public function repositoryUrl(): string
    {
        return $this->repositoryUrl;
    }

    /** @return list<string> */
    public function knownServiceNames(): array
    {
        return $this->knownServiceNames;
    }

    public function toDTO(): ProjectDTO
    {
        return new ProjectDTO(
            $this->id->toString(),
            $this->name->toString(),
            $this->hash->toString(),
            $this->repositoryUrl,
            $this->teamId,
        );
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
