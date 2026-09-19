<?php

declare(strict_types=1);

namespace App\AppSource\Application;

use App\AppSource\Domain\AppSource;
use App\AppSource\Domain\Port\AppSourceRepository;
use App\AppSource\Domain\ValueObject\AppSourceId;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ApplicationService
{
    /**
     * Único adaptador de proveedor soportado hoy (ver appsource/discovery.md) —
     * `ProjectRegistered` no lo lleva en el payload porque Project no lo conoce,
     * es AppSource quien decide su propio tipo de adaptador.
     */
    private const DEFAULT_PROVIDER = 'github';

    public function __construct(
        private AppSourceRepository $appSources,
        private MessageBusInterface $eventBus,
    ) {
    }

    public function registerAppSource(string $projectId, string $repositoryUrl): string
    {
        $appSource = AppSource::register($projectId, $repositoryUrl, self::DEFAULT_PROVIDER);

        $this->appSources->save($appSource);

        return $appSource->id()->toString();
    }

    public function recordRevision(string $appSourceId, string $revision): void
    {
        $appSource = $this->appSources->get(AppSourceId::fromString($appSourceId));

        $events = $appSource->recordRevision($revision);

        $this->appSources->save($appSource);

        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
