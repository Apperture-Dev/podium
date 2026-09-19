<?php

declare(strict_types=1);

namespace App\Project\Application\EventHandler;

use App\Project\Application\Message\SourceChanged;
use App\Project\Domain\Port\PodiumManifestReader;
use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\ValueObject\ProjectId;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'event.bus')]
final readonly class SourceChangedHandler
{
    public function __construct(
        private ProjectRepository $projects,
        private PodiumManifestReader $manifestReader,
        #[Autowire(service: 'event.bus')]
        private MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(SourceChanged $message): void
    {
        $project = $this->projects->get(ProjectId::fromString($message->projectId));

        $declaredServices = $this->manifestReader->read($message->repositoryUrl, $message->revision);

        $events = $project->processSourceChanged(
            $message->revision,
            $message->repositoryUrl,
            $message->provider,
            $declaredServices,
        );

        $this->projects->save($project);

        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
