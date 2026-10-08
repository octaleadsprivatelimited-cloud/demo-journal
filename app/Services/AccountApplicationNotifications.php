<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Notifications\AccountApplicationReceivedNotification;
use App\Notifications\AccountApplicationStatusNotification;
use Illuminate\Database\Eloquent\Collection;

final class AccountApplicationNotifications
{
    public function submitted(User $applicant): void
    {
        if (! $applicant->hasVerifiedEmail()) {
            $applicant->sendEmailVerificationNotification();
        }

        $applicant->notify(new AccountApplicationStatusNotification($applicant, 'pending'));
        app(EditorialOfficeNotifications::class)->send(
            $this->approvers(),
            new AccountApplicationReceivedNotification($applicant),
            'account_notification_email',
        );
    }

    public function decided(User $applicant): void
    {
        $notification = new AccountApplicationStatusNotification($applicant, $applicant->status);
        $applicant->notify($notification);
        app(EditorialOfficeNotifications::class)->send(
            $this->approvers()->reject(fn (User $approver): bool => $approver->is($applicant)),
            clone $notification,
            'account_notification_email',
            [$applicant->email],
        );
    }

    private function approvers(): Collection
    {
        return User::query()->active()->whereHas('roles', fn ($query) => $query->where('slug', 'super-admin'))->get();
    }
}
