<?php

declare(strict_types=1);

namespace App\AppManager\Application\EventHandler;

use App\AppManager\Application\ApplicationService;
use App\AppManager\Application\Message\BuildFailed;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BuildFailedHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(BuildFailed $message): void
    {
        $this->applicationService->markBuildFailed($message->projectId, $message->serviceName);
    }
}
