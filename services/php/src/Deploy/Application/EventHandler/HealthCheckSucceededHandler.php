<?php

declare(strict_types=1);

namespace App\Deploy\Application\EventHandler;

use App\Deploy\Application\ApplicationService;
use App\Deploy\Application\Message\HealthCheckSucceeded;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class HealthCheckSucceededHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(HealthCheckSucceeded $message): void
    {
        $this->applicationService->completeDeployAttempt($message->deployAttemptId);
    }
}
