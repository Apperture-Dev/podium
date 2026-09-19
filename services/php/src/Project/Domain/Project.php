<?php

declare(strict_types=1);

namespace App\Project\Domain;

use App\Project\Domain\Event\ApplicationSourceChanged;
use App\Project\Domain\Event\ServiceDiscovered;
use App\Project\Domain\ValueObject\DeclaredService;
use App\Project\Domain\ValueObject\Hash;
use App\Project\Domain\ValueObject\ProjectId;

final class Project
{
    /** @var list<string> */
    private array $knownServiceNames = [];

    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly ProjectId $id,
        private readonly Hash $hash,
        private readonly string $teamId,
        private readonly string $repositoryUrl,
    ) {
    }

    public static function register(string $repositoryUrl, string $teamId): self
    {
        return new self(ProjectId::generate(), Hash::generate(), $teamId, $repositoryUrl);
    }

    /** @param list<string> $knownServiceNames */
    public static function rehydrate(
        ProjectId $id,
        Hash $hash,
        string $teamId,
        string $repositoryUrl,
        array $knownServiceNames,
    ): self {
        $project = new self($id, $hash, $teamId, $repositoryUrl);
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

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /** @return list<object> */
    private function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
