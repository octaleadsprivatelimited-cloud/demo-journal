<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Services\MicrosoftEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DeliverWorkflowEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(MicrosoftEmailService $mail): void
    {
        $delivery = NotificationDelivery::query()->findOrFail($this->deliveryId);
        if ($delivery->status === 'sent') { return; }

        try {
            $mail->send($delivery);
            $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'failure_reason' => null])->save();
        } catch (Throwable $exception) {
            $delivery->forceFill(['status' => 'failed', 'failed_at' => now(), 'retry_count' => $delivery->retry_count + 1, 'failure_reason' => str($exception->getMessage())->limit(1000)])->save();
            throw $exception;
        }
    }
}
