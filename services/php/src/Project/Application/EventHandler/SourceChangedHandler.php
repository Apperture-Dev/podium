<?php

declare(strict_types=1);

namespace App\Project\Application\EventHandler;

use App\AppSource\Domain\Event\SourceChanged;
use App\Project\Application\ApplicationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Adaptador fino: traduce el evento entrante (publicado por el BC AppSource,
 * ver docs/podium-domain/event-catalog.md) a una llamada al ApplicationService.
 * Sin lógica propia — coordina, no decide.
 *
 * Escucha la clase de evento real de AppSource, no una copia local: al ser
 * un monorepo PHP (mismo proceso, mismo autoload), Messenger enruta por
 * clase exacta del mensaje — una clase local homónima nunca sería la misma
 * que Symfony reconstruye al decodificar el mensaje real desde Redis.
 */
#[AsMessageHandler]
final readonly class SourceChangedHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(SourceChanged $message): void
    {
        $this->applicationService->processSourceChanged(
            $message->projectId,
            $message->revision,
            $message->repositoryUrl,
            $message->provider,
        );
    }
}
