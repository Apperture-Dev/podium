<?php

declare(strict_types=1);

namespace App\Project\Application;

use App\Project\Domain\Port\PodiumManifestReader;
use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\ValueObject\ProjectId;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ApplicationService
{
    public function __construct(
        private ProjectRepository $projects,
        private PodiumManifestReader $manifestReader,
        private MessageBusInterface $eventBus,
    ) {
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
