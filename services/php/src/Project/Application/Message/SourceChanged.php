<?php

declare(strict_types=1);

namespace App\Project\Application\Message;

/**
 * Contrato entrante publicado por el BC AppSource (ver docs/podium-domain/event-catalog.md).
 * No es un evento de dominio de Project — es la forma en la que Project recibe un evento
 * de otro BC; se traduce a comportamiento del agregado dentro del handler.
 */
final readonly class SourceChanged
{
    public function __construct(
        public string $projectId,
        public string $revision,
        public string $repositoryUrl,
        public string $provider,
    ) {
    }
}
