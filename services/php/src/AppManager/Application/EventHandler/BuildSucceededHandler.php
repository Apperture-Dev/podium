<?php

declare(strict_types=1);

namespace App\AppManager\Application\EventHandler;

use App\AppManager\Application\ApplicationService;
use App\Build\Domain\Event\BuildSucceeded;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BuildSucceededHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(BuildSucceeded $event): void
    {
        $this->applicationService->markBuildSucceeded($event->projectId, $event->serviceName, $event->image, $event->deployEnvVars, $event->databaseDeclaration);
    }
}
