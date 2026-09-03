<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\ContactSubmissionReceived;
use App\Models\ContactSubmission;

class ContactSubmissionObserver
{
    public function created(ContactSubmission $submission): void
    {
        ContactSubmissionReceived::dispatch($submission);
    }
}
