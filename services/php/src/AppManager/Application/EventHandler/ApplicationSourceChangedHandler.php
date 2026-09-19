<?php

declare(strict_types=1);

namespace App\AppManager\Application\EventHandler;

use App\AppManager\Application\ApplicationService;
use App\Project\Domain\Event\ApplicationSourceChanged;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ApplicationSourceChangedHandler
{
    public function __construct(
        private ApplicationService $applicationService,
    ) {
    }

    public function __invoke(ApplicationSourceChanged $message): void
    {
        $this->applicationService->markSourceChanged(
            $message->projectId,
            $message->serviceName,
            $message->revision,
            $message->repositoryUrl,
            $message->provider,
        );
    }
}
