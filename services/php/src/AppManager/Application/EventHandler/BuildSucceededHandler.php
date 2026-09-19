<?php

declare(strict_types=1);

namespace App\AppManager\Application\EventHandler;

use App\AppManager\Application\ApplicationService;
use App\AppManager\Application\Message\BuildSucceeded;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BuildSucceededHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(BuildSucceeded $message): void
    {
        $this->applicationService->markBuildSucceeded($message->projectId, $message->serviceName, $message->image);
    }
}
