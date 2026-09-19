<?php

declare(strict_types=1);

namespace App\AppManager\Application\EventHandler;

use App\AppManager\Application\ApplicationService;
use App\Deploy\Domain\Event\DeployFailed;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeployFailedHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(DeployFailed $event): void
    {
        $this->applicationService->markDeployFailed($event->projectId, $event->serviceName);
    }
}
