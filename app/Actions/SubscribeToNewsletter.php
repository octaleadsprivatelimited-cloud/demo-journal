<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SubscriberStatus;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Str;

class SubscribeToNewsletter
{
    public function execute(string $email, ?string $name = null, ?string $source = null): NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::withTrashed()->firstOrNew([
            'email' => Str::lower(trim($email)),
        ]);

        if ($subscriber->trashed()) {
            $subscriber->restore();
        }

        $subscriber->forceFill([
            'name' => $name ?: $subscriber->name,
            'source' => $source ?: $subscriber->source,
            'status' => SubscriberStatus::Active,
            'subscribed_at' => now(),
            'unsubscribed_at' => null,
        ])->save();

        return $subscriber;
    }
}
