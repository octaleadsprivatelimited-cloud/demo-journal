<?php

namespace App\Http\Controllers\Public;

use App\Http\Requests\Public\SearchRequest;
use App\Models\Author;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Contracts\View\View;

class SearchController extends PublicController
{
    public function __invoke(SearchRequest $request): View
    {
        $filters = $request->validated();
        $query = $this->publishedArticles();

        $query
            ->when($filters['q'] ?? null, fn ($builder, $term) => $builder->search($term))
            ->when($filters['title'] ?? null, function ($builder, $title): void {
                $escaped = addcslashes($title, '%_');
                $builder->where('title', 'like', "%{$escaped}%");
            })
            ->when($filters['author'] ?? null, fn ($builder, $author) => $builder->whereHas(
                'authors',
                fn ($authorQuery) => $authorQuery->where('slug', $author),
            ))
            ->when($filters['category'] ?? null, fn ($builder, $category) => $builder->whereHas(
                'category',
                fn ($categoryQuery) => $categoryQuery->where('slug', $category),
            ))
            ->when($filters['tag'] ?? null, fn ($builder, $tag) => $builder->whereHas(
                'tags',
                fn ($tagQuery) => $tagQuery->where('slug', $tag),
            ))
            ->when($filters['from'] ?? null, fn ($builder, $date) => $builder->whereDate('published_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($builder, $date) => $builder->whereDate('published_at', '<=', $date))
            ->orderByDesc('is_featured')
            ->latest('published_at');

        $hasSearch = collect($filters)->filter(fn ($value) => filled($value))->isNotEmpty();
        $articles = $hasSearch ? $query->paginate(12)->withQueryString() : null;

        $categories = Category::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']);
        $tags = Tag::query()->whereHas('articles', fn ($articles) => $articles->published())->orderBy('name')->limit(50)->get(['id', 'name', 'slug']);
        $authors = Author::query()->where('is_active', true)->whereHas('articles', fn ($articles) => $articles->published())->orderBy('name')->get(['id', 'name', 'slug']);

        return view('public.search', $this->publicViewData(compact(
            'articles',
            'categories',
            'tags',
            'authors',
            'filters',
            'hasSearch',
        )));
    }
}
