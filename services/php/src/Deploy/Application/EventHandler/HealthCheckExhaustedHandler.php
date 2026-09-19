<?php

declare(strict_types=1);

namespace App\Deploy\Application\EventHandler;

use App\Deploy\Application\ApplicationService;
use App\Deploy\Application\Message\HealthCheckExhausted;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class HealthCheckExhaustedHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(HealthCheckExhausted $message): void
    {
        $this->applicationService->failDeployAttempt($message->deployAttemptId, $message->errorMessage, $message->retryCount);
    }
}
