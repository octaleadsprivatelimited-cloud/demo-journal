<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NewsletterSubscriber;
use App\Models\User;

class NewsletterSubscriberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('newsletter.manage');
    }

    public function view(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->hasPermission('newsletter.manage');
    }

    public function update(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->hasPermission('newsletter.manage');
    }

    public function delete(User $user, NewsletterSubscriber $subscriber): bool
    {
        return $user->hasPermission('newsletter.manage');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('newsletter.manage');
    }
}
