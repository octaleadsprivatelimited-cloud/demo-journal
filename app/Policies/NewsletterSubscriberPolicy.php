<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NewsletterSubscriber;
use App\Models\User;

class NewsletterSubscriberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole('super-admin', 'admin');
    }

    public function view(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->hasAnyRole('super-admin', 'admin');
    }

    public function update(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->hasAnyRole('super-admin', 'admin');
    }

    public function delete(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->hasAnyRole('super-admin', 'admin');
    }

    public function export(User $user): bool
    {
        return $user->hasAnyRole('super-admin', 'admin');
    }
}
