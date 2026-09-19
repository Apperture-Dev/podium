<?php

declare(strict_types=1);

namespace App\Build\Application\EventHandler;

use App\Build\Application\ApplicationService;
use App\Build\Application\Message\JobFailed;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class JobFailedHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(JobFailed $message): void
    {
        $this->applicationService->failBuildJob($message->buildJobId, $message->errorMessage);
    }
}
