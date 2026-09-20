<?php

declare(strict_types=1);

namespace App\AppManager\Application\EventHandler;

use App\AppManager\Application\ApplicationService;
use App\Project\Domain\Event\ServiceDiscovered;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ServiceDiscoveredHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(ServiceDiscovered $message): void
    {
        $this->applicationService->registerApplication(
            $message->serviceName,
            $message->projectId,
            $message->lang,
            $message->framework,
            $message->revision,
            $message->repositoryUrl,
            $message->provider,
        );
    }
}
