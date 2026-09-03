<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SubscriberStatus;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Notifications\NewsletterCampaignNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class SendNewsletterCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly int $campaignId)
    {
        $this->onQueue('mail');
    }

    public function handle(): void
    {
        $campaign = NewsletterCampaign::query()->find($this->campaignId);
        if (! $campaign || ! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return;
        }
        if ($campaign->scheduled_for?->isFuture()) {
            $this->release((int) $campaign->scheduled_for->diffInSeconds(now()));

            return;
        }
        $claimed = NewsletterCampaign::query()->whereKey($campaign->getKey())->whereIn('status', ['draft', 'scheduled'])->update(['status' => 'sending', 'failure_message' => null]);
        if ($claimed !== 1) {
            return;
        } $campaign->refresh();
        $count = 0;
        try {
            NewsletterSubscriber::query()->where('status', SubscriberStatus::Active)->orderBy('id')->chunkById(250, function ($subscribers) use ($campaign, &$count): void {
                foreach ($subscribers as $subscriber) {
                    Notification::route('mail', $subscriber->email)->notify(new NewsletterCampaignNotification($campaign, (string) $subscriber->token));
                    $count++;
                }
            });
            $campaign->update(['status' => 'sent', 'recipient_count' => $count, 'sent_at' => now()]);
        } catch (Throwable $exception) {
            $campaign->update(['status' => 'failed', 'recipient_count' => $count, 'failure_message' => str($exception->getMessage())->limit(2000)]);
            throw $exception;
        }
    }
}
