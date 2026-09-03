<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDelivery extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['payload' => 'array', 'queued_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime']; }
    public function article(): BelongsTo { return $this->belongsTo(Article::class); }
    public function recipient(): BelongsTo { return $this->belongsTo(User::class, 'recipient_id'); }
}
