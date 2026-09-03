<?php

declare(strict_types=1);

namespace App\Enums;

enum ReviewRecommendation: string
{
    case Approve = 'approve';
    case MinorRevision = 'minor_revision';
    case MajorRevision = 'major_revision';
    case Reject = 'reject';
}
