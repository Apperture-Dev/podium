<?php

declare(strict_types=1);

namespace App\AppManager\Domain\ValueObject;

use App\AppManager\Domain\ApplicationState;

/**
 * Forma canónica del estado de una Application en un instante — mismo shape
 * reutilizado en vivo (Application::toDTO()) y dentro de cada ApplicationHistoryLog.
 * Solo primitivos, nunca objetos de dominio.
 */
final readonly class ApplicationDTO
{
    public function __construct(
        public string $serviceName,
        public string $projectId,
        public string $teamId,
        public string $framework,
        public \DateTimeImmutable $createdAt,
        public ApplicationState $state,
        public string $version,
        public bool $hasPendingSourceChange,
    ) {
    }

    /** @return array{serviceName: string, projectId: string, teamId: string, framework: string, createdAt: string, state: string, version: string, hasPendingSourceChange: bool} */
    public function toArray(): array
    {
        return [
            'serviceName' => $this->serviceName,
            'projectId' => $this->projectId,
            'teamId' => $this->teamId,
            'framework' => $this->framework,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'state' => $this->state->value,
            'version' => $this->version,
            'hasPendingSourceChange' => $this->hasPendingSourceChange,
        ];
    }

    /** @param array{serviceName: string, projectId: string, teamId: string, framework: string, createdAt: string, state: string, version: string, hasPendingSourceChange: bool} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['serviceName'],
            $data['projectId'],
            $data['teamId'],
            $data['framework'],
            new \DateTimeImmutable($data['createdAt']),
            ApplicationState::from($data['state']),
            $data['version'],
            $data['hasPendingSourceChange'],
        );
    }
}
