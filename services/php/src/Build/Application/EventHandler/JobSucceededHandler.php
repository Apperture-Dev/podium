<?php

declare(strict_types=1);

namespace App\Build\Application\EventHandler;

use App\Build\Application\ApplicationService;
use App\Build\Application\Message\JobSucceeded;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class JobSucceededHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(JobSucceeded $message): void
    {
        $this->applicationService->completeBuildJob(
            $message->buildJobId,
            $message->image,
            $message->buildEnvVars,
            $message->deployEnvVars,
            $message->databaseDeclaration,
        );
    }
}
