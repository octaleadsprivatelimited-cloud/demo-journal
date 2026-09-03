<?php

declare(strict_types=1);

namespace App\Enums;

enum ReviewStatus: string
{
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
}
