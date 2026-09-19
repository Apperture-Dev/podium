<?php

declare(strict_types=1);

namespace App\AppManager\Domain\Port;

use App\AppManager\Domain\Application;
use App\AppManager\Domain\ValueObject\ApplicationId;

interface ApplicationRepository
{
    public function get(ApplicationId $id): Application;

    /**
     * Los eventos entrantes de Build/Deploy/Project identifican a la Application
     * por serviceName+projectId (único dentro del Project), no por su id técnico
     * — ningún otro BC lo conoce.
     */
    public function findByProjectIdAndServiceName(string $projectId, string $serviceName): ?Application;

    /** @return list<Application> */
    public function findByProjectId(string $projectId): array;

    public function save(Application $application): void;
}
