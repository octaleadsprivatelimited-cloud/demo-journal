<?php

namespace App\Http\Controllers\Public;

use App\Models\Category;
use Illuminate\Contracts\View\View;

class CategoryController extends PublicController
{
    public function index(): View
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query
                    ->where('is_active', true)
                    ->withCount(['articles as published_articles_count' => fn ($articles) => $articles->published()])
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->withCount(['articles as published_articles_count' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('public.categories.index', $this->publicViewData(compact('categories')));
    }

    public function show(string $slug): View
    {
        $category = Category::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->with([
                'parent:id,name,slug',
                'children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
                'seoMetadata',
            ])
            ->firstOrFail();

        $categoryIds = $category->children->modelKeys();
        $categoryIds[] = $category->getKey();

        $articles = $this->publishedArticles()
            ->whereIn('category_id', $categoryIds)
            ->latest('published_at')
            ->paginate(12);

        return view('public.categories.show', $this->publicViewData(compact('category', 'articles')));
    }
}
