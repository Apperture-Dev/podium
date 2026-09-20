<?php

declare(strict_types=1);

namespace App\Deploy\Application\EventHandler;

use App\AppManager\Domain\Event\ApplicationDeployRequested;
use App\Deploy\Application\ApplicationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ApplicationDeployRequestedHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(ApplicationDeployRequested $event): void
    {
        $this->applicationService->requestDeploy(
            $event->serviceName,
            $event->projectId,
            $event->version,
            $event->image,
            $event->port,
            $event->deployEnvVars,
            $event->databaseDeclaration,
        );
    }
}
