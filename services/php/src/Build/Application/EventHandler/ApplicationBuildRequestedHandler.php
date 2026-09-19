<?php

declare(strict_types=1);

namespace App\Build\Application\EventHandler;

use App\AppManager\Domain\Event\ApplicationBuildRequested;
use App\Build\Application\ApplicationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ApplicationBuildRequestedHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(ApplicationBuildRequested $event): void
    {
        $this->applicationService->startBuildJob(
            $event->serviceName,
            $event->projectId,
            $event->templateId,
            $event->version,
            $event->revision,
            $event->repositoryUrl,
            $event->provider,
        );
    }
}
