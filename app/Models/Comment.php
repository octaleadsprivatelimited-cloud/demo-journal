<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommentStatus;
use App\Models\Concerns\HasAuditLogs;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([AuditObserver::class])]
class Comment extends Model
{
    use HasAuditLogs, HasFactory, SoftDeletes;

    protected $fillable = [
        'article_id', 'parent_id', 'user_id', 'guest_name', 'guest_email', 'body',
        'status', 'ip_hash', 'approved_at',
    ];

    protected $hidden = ['guest_email', 'ip_hash'];

    protected function casts(): array
    {
        return ['status' => CommentStatus::class, 'approved_at' => 'datetime'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class)->withTrashed();
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', CommentStatus::Approved);
    }
}
