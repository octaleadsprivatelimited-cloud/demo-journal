<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Models\Concerns\HasAuditLogs;
use App\Models\Concerns\HasUniqueSlug;
use App\Observers\ArticleObserver;
use App\Observers\AuditObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy([ArticleObserver::class, AuditObserver::class])]
class Article extends Model
{
    use HasAuditLogs, HasFactory, HasUniqueSlug, SoftDeletes;

    protected $fillable = [
        'public_id', 'category_id', 'created_by_id', 'assigned_editor_id', 'title', 'slug',
        'subtitle', 'excerpt', 'abstract', 'content', 'keywords', 'references', 'publication_type',
        'doi', 'featured_image_path', 'pdf_path', 'status', 'is_featured', 'is_trending',
        'comments_enabled', 'pdf_download_enabled', 'reading_time_minutes', 'view_count',
        'submitted_at', 'approved_at', 'scheduled_for', 'published_at', 'rejected_at',
        'article_number', 'revision_round', 'metadata_validated_at', 'metadata_validated_by_id',
    ];

    protected $attributes = [
        'status' => 'draft',
        'publication_type' => 'article',
        'comments_enabled' => true,
        'pdf_download_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'keywords' => 'array',
            'references' => 'array',
            'is_featured' => 'boolean',
            'is_trending' => 'boolean',
            'comments_enabled' => 'boolean',
            'pdf_download_enabled' => 'boolean',
            'reading_time_minutes' => 'integer',
            'view_count' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
            'rejected_at' => 'datetime',
            'revision_round' => 'integer',
            'metadata_validated_at' => 'datetime',
        ];
    }

    protected function getSlugSourceColumn(): string
    {
        return 'title';
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function assignedEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_editor_id');
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'article_authors')
            ->withPivot(['is_corresponding', 'sort_order'])
            ->orderByPivot('sort_order');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'article_tags');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ArticleVersion::class)->orderByDesc('version_number');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class)->orderByDesc('round');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(EditorDecision::class)->latest('decided_at');
    }

    public function doiRecord(): HasOne
    {
        return $this->hasOne(DoiRecord::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(ArticleView::class);
    }

    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeTrending(Builder $query): Builder
    {
        return $query->where('is_trending', true);
    }

    public function scopeStatus(Builder $query, ArticleStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof ArticleStatus ? $status->value : $status);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $plainTerm = trim($term);

        if ($query->getConnection()->getDriverName() === 'pgsql') {
            return $query->where(function (Builder $query) use ($plainTerm): void {
                $query->whereRaw(
                    "to_tsvector('simple', coalesce(title, '') || ' ' || coalesce(subtitle, '') || ' ' || coalesce(abstract, '') || ' ' || coalesce(content, '')) @@ websearch_to_tsquery('simple', ?)",
                    [$plainTerm],
                )
                    ->orWhereHas('authors', fn (Builder $authors) => $authors->where('name', 'ilike', '%'.$plainTerm.'%'))
                    ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'ilike', '%'.$plainTerm.'%'));
            });
        }

        $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $plainTerm).'%';

        return $query->where(function (Builder $query) use ($term): void {
            $query->where('title', 'like', $term)
                ->orWhere('subtitle', 'like', $term)
                ->orWhere('abstract', 'like', $term)
                ->orWhere('content', 'like', $term)
                ->orWhereHas('authors', fn (Builder $authors) => $authors->where('name', 'like', $term))
                ->orWhereHas('tags', fn (Builder $tags) => $tags->where('name', 'like', $term));
        });
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->created_by_id === $user->getKey()
            || $this->authors()->where('user_id', $user->getKey())->exists();
    }
}
