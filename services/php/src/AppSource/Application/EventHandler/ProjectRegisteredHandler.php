<?php

declare(strict_types=1);

namespace App\AppSource\Application\EventHandler;

use App\AppSource\Application\ApplicationService;
use App\Project\Domain\Event\ProjectRegistered;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Adaptador fino: traduce el evento entrante (publicado por el BC Project) a
 * una llamada al ApplicationService. Sin lógica propia — coordina, no decide.
 *
 * Escucha la clase de evento real de Project, no una copia local — ver la
 * nota en Project\Application\EventHandler\SourceChangedHandler sobre por
 * qué, en este monorepo PHP, Messenger necesita la clase exacta.
 */
#[AsMessageHandler]
final readonly class ProjectRegisteredHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(ProjectRegistered $message): void
    {
        $this->applicationService->registerAppSource($message->projectId, $message->repositoryUrl);
    }
}
