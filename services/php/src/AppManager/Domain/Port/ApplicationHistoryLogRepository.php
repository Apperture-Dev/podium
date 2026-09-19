<?php

declare(strict_types=1);

namespace App\AppManager\Domain\Port;

use App\AppManager\Domain\ApplicationHistoryLog;
use App\AppManager\Domain\ValueObject\ApplicationId;

interface ApplicationHistoryLogRepository
{
    /** @return list<ApplicationHistoryLog> ordenados de más antiguo a más reciente */
    public function findByApplicationId(ApplicationId $applicationId): array;
}
