<?php

declare(strict_types=1);

namespace App\Project\Application\EventHandler;

use App\Project\Application\ApplicationService;
use App\Project\Application\Message\SourceChanged;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Adaptador fino: traduce el mensaje entrante (contrato del BC AppSource,
 * ver docs/podium-domain/event-catalog.md) a una llamada al ApplicationService.
 * Sin lógica propia — coordina, no decide.
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
