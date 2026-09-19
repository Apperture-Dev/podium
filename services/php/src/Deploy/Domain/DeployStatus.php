<?php

declare(strict_types=1);

namespace App\Deploy\Domain;

enum DeployStatus: string
{
    case Pending = 'Pending';
    case Succeeded = 'Succeeded';
    case Failed = 'Failed';
}
