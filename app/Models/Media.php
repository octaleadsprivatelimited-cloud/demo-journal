<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAuditLogs;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([AuditObserver::class])]
class Media extends Model
{
    use HasAuditLogs, HasFactory, SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'public_id', 'mediable_type', 'mediable_id', 'uploaded_by_id', 'disk', 'path',
        'original_name', 'mime_type', 'extension', 'size_bytes', 'checksum', 'collection',
        'visibility', 'alt_text', 'caption', 'width', 'height', 'metadata',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer', 'metadata' => 'array'];
    }

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
