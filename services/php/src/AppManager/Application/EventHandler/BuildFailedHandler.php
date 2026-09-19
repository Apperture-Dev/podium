<?php

declare(strict_types=1);

namespace App\AppManager\Application\EventHandler;

use App\AppManager\Application\ApplicationService;
use App\Build\Domain\Event\BuildFailed;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BuildFailedHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(BuildFailed $event): void
    {
        $this->applicationService->markBuildFailed($event->projectId, $event->serviceName);
    }
}
