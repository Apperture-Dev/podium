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
        // deployEnvVars/databaseDeclaration siguen en el mensaje porque forman
        // parte del contrato con el lanzador Go, pero ya no los consume nadie:
        // lo que la aplicación necesita para correr lo lee Deploy del yaml.
        $this->applicationService->completeBuildJob(
            $message->buildJobId,
            $message->image,
            $message->buildEnvVars,
        );
    }
}
