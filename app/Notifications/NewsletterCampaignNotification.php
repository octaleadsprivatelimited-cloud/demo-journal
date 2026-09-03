<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\NewsletterCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewsletterCampaignNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly NewsletterCampaign $campaign, private readonly string $unsubscribeToken)
    {
        $this->onQueue('mail');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->campaign->subject)->greeting($this->campaign->subject);
        if ($this->campaign->preview_text) {
            $mail->line($this->campaign->preview_text);
        }
        foreach (preg_split('/\r\n|\r|\n/', trim(strip_tags($this->campaign->content))) ?: [] as $line) {
            if (filled($line)) {
                $mail->line($line);
            }
        }

        return $mail->action('Read the latest journal', route('articles.index'))->line('You are receiving this because you subscribed to '.config('app.name').'.')->line('[Unsubscribe from future issues]('.route('newsletter.unsubscribe', $this->unsubscribeToken).')');
    }
}
