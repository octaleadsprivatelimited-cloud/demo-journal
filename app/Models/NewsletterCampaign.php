<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class NewsletterCampaign extends Model
{
    use SoftDeletes;

    protected $fillable = ['public_id', 'created_by_id', 'subject', 'preview_text', 'content', 'status', 'recipient_count', 'scheduled_for', 'sent_at', 'failure_message'];

    protected function casts(): array
    {
        return ['recipient_count' => 'integer', 'scheduled_for' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
