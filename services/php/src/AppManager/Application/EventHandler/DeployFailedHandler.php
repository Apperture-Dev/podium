<?php

declare(strict_types=1);

namespace App\AppManager\Application\EventHandler;

use App\AppManager\Application\ApplicationService;
use App\AppManager\Application\Message\DeployFailed;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeployFailedHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(DeployFailed $message): void
    {
        $this->applicationService->markDeployFailed($message->projectId, $message->serviceName);
    }
}
