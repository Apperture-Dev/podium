<?php

declare(strict_types=1);

namespace App\Build\Domain;

enum BuildStatus: string
{
    case Pending = 'Pending';
    case Running = 'Running';
    case Succeeded = 'Succeeded';
    case Failed = 'Failed';
}
