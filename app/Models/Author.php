<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasAuditLogs;
use App\Models\Concerns\HasUniqueSlug;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([AuditObserver::class])]
class Author extends Model
{
    use HasAuditLogs, HasFactory, HasUniqueSlug, SoftDeletes;

    protected $fillable = [
        'department', 'country', 'user_id', 'name', 'slug', 'email', 'biography', 'designation', 'organization',
        'avatar_path', 'website_url', 'affiliation', 'orcid', 'social_links', 'is_verified', 'is_active',
    ];

    protected function casts(): array
    {
        return ['social_links' => 'array', 'is_verified' => 'boolean', 'is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_authors')
            ->withPivot(['is_corresponding', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    public function publishedArticles(): BelongsToMany
    {
        return $this->articles()->published();
    }

    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }
}
