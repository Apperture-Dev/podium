<?php

declare(strict_types=1);

namespace App\Deploy\Domain\Event;

final readonly class DeployAttemptRequested
{
    /**
     * `database` viaja ya resuelto (`mode`/`urlVar`/`vars`), exactamente como
     * los espera el chart: la regla de qué forma del podium.yaml gana es
     * dominio y se decide aquí, no en el lanzador ni en la plantilla de Helm.
     *
     * @param array<string, string>                                              $envVars
     * @param array{mode: string, urlVar: string, vars: array<string, string>}   $database
     */
    public function __construct(
        public string $deployAttemptId,
        public string $hash,
        public string $serviceName,
        public string $image,
        public int $port,
        public array $envVars,
        public array $database,
    ) {
    }
}
