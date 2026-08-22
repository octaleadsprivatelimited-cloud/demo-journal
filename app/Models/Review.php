<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewRecommendation;
use App\Enums\ReviewStatus;
use App\Models\Concerns\HasAuditLogs;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([AuditObserver::class])]
class Review extends Model
{
    use HasAuditLogs, HasFactory;

    protected $fillable = [
        'article_id', 'submission_id', 'reviewer_id', 'assigned_by_id', 'status',
        'recommendation', 'comments_to_author', 'confidential_comments', 'due_at',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReviewStatus::class,
            'recommendation' => ReviewRecommendation::class,
            'due_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReviewComment::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [ReviewStatus::Assigned, ReviewStatus::InProgress]);
    }
}
