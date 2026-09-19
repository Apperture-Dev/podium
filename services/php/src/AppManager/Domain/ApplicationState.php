<?php

declare(strict_types=1);

namespace App\AppManager\Domain;

enum ApplicationState: string
{
    case Created = 'Created';
    case Building = 'Building';
    case Built = 'Built';
    case Deploying = 'Deploying';
    case Deployed = 'Deployed';
    case BuildFailed = 'BuildFailed';
    case DeployFailed = 'DeployFailed';
}
