<?php

declare(strict_types=1);

namespace App\AppSource\Domain;

use App\AppSource\Domain\Event\SourceChanged;
use App\AppSource\Domain\ValueObject\AppSourceId;

final class AppSource
{
    /** @var list<object> */
    private array $recordedEvents = [];

    private function __construct(
        private readonly AppSourceId $id,
        private readonly string $projectId,
        private readonly string $repositoryUrl,
        private readonly string $provider,
        private ?string $revision,
    ) {
    }

    /** Reacciona a `ProjectRegistered` — sin última revisión conocida todavía. */
    public static function register(string $projectId, string $repositoryUrl, string $provider): self
    {
        return new self(AppSourceId::generate(), $projectId, $repositoryUrl, $provider, null);
    }

    public static function rehydrate(
        AppSourceId $id,
        string $projectId,
        string $repositoryUrl,
        string $provider,
        ?string $revision,
    ): self {
        return new self($id, $projectId, $repositoryUrl, $provider, $revision);
    }

    /**
     * Solo publica SourceChanged si la revision recibida difiere de la última
     * conocida — el mecanismo real que la observa (poller, webhook) es
     * infraestructura, no dominio.
     *
     * @return list<SourceChanged>
     */
    public function recordRevision(string $revision): array
    {
        if ($revision === $this->revision) {
            return [];
        }

        $this->revision = $revision;
        $this->record(new SourceChanged($this->projectId, $revision, $this->repositoryUrl, $this->provider));

        return $this->releaseEvents();
    }

    public function id(): AppSourceId
    {
        return $this->id;
    }

    public function projectId(): string
    {
        return $this->projectId;
    }

    public function repositoryUrl(): string
    {
        return $this->repositoryUrl;
    }

    public function provider(): string
    {
        return $this->provider;
    }

    public function revision(): ?string
    {
        return $this->revision;
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
