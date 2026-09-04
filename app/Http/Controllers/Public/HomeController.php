<?php

namespace App\Http\Controllers\Public;

use App\Models\Author;
use App\Models\Category;
use Illuminate\Contracts\View\View;

class HomeController extends PublicController
{
    public function __invoke(): View
    {
        $heroArticles = $this->publishedArticles()
            ->where(fn ($query) => $query->where('is_featured', true)->orWhere('is_homepage_latest', true))
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->orderByDesc('id')
            ->get();

        $latest = $this->publishedArticles()
            ->latest('published_at')
            ->limit(7)
            ->get();

        $trending = $this->publishedArticles()
            ->trending()
            ->orderByDesc('view_count')
            ->latest('published_at')
            ->limit(4)
            ->get();

        if ($trending->isEmpty()) {
            $trending = $this->publishedArticles()
                ->orderByDesc('view_count')
                ->latest('published_at')
                ->limit(4)
                ->get();
        }

        $authors = Author::query()
            ->where('is_active', true)
            ->whereHas('articles', fn ($query) => $query->published())
            ->withCount(['articles as published_articles_count' => fn ($query) => $query->published()])
            ->orderByDesc('is_verified')
            ->orderByDesc('published_articles_count')
            ->limit(4)
            ->get();

        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->whereHas('articles', fn ($query) => $query->published())
            ->withCount(['articles as published_articles_count' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(8)
            ->get();

        return view('public.home', $this->publicViewData(compact(
            'heroArticles',
            'latest',
            'trending',
            'authors',
            'categories',
        )));
    }
}
