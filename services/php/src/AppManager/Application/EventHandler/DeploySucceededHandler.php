<?php

declare(strict_types=1);

namespace App\AppManager\Application\EventHandler;

use App\AppManager\Application\ApplicationService;
use App\AppManager\Application\Message\DeploySucceeded;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeploySucceededHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(DeploySucceeded $message): void
    {
        $this->applicationService->markDeploySucceeded($message->projectId, $message->serviceName);
    }
}
