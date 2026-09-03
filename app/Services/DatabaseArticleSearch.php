<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use App\Models\SearchLog;
use App\Services\Contracts\ArticleSearch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DatabaseArticleSearch implements ArticleSearch
{
    /** @param array<string, mixed> $filters */
    public function search(string $query, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $startedAt = microtime(true);
        $perPage = max(1, min($perPage, 100));

        $builder = Article::query()
            ->published()
            ->with(['category', 'authors', 'tags'])
            ->when(trim($query) !== '', fn (Builder $builder) => $builder->search($query))
            ->when($filters['category'] ?? null, fn (Builder $builder, mixed $slug) => $builder->whereHas('category', fn (Builder $category) => $category->where('slug', $slug)))
            ->when($filters['tag'] ?? null, fn (Builder $builder, mixed $slug) => $builder->whereHas('tags', fn (Builder $tag) => $tag->where('slug', $slug)))
            ->when($filters['author'] ?? null, fn (Builder $builder, mixed $slug) => $builder->whereHas('authors', fn (Builder $author) => $author->where('slug', $slug)))
            ->when($filters['date_from'] ?? null, fn (Builder $builder, mixed $date) => $builder->whereDate('published_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $builder, mixed $date) => $builder->whereDate('published_at', '<=', $date));

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $builder->orderBy('published_at'),
            'popular' => $builder->orderByDesc('view_count')->orderByDesc('published_at'),
            default => $builder->orderByDesc('published_at'),
        };

        $results = $builder->paginate($perPage)->withQueryString();
        $request = app()->bound('request') ? request() : null;

        SearchLog::query()->create([
            'user_id' => auth()->id(),
            'query' => trim($query),
            'filters' => $filters,
            'results_count' => $results->total(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'session_id' => $request?->hasSession() ? $request->session()->getId() : null,
            'ip_hash' => $request?->ip() ? hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')) : null,
            'searched_at' => now(),
        ]);

        return $results;
    }
}
