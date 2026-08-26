<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubmissionStatus;
use App\Models\Concerns\HasAuditLogs;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([AuditObserver::class])]
class Submission extends Model
{
    use HasAuditLogs, HasFactory;

    protected $fillable = [
        'article_id', 'article_version_id', 'submitted_by_id', 'round', 'status',
        'cover_letter', 'submitted_at', 'decision_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'round' => 'integer',
            'submitted_at' => 'datetime',
            'decision_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class)->withTrashed();
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ArticleVersion::class, 'article_version_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [SubmissionStatus::Pending, SubmissionStatus::InReview]);
    }
}
