<?php

namespace App\Http\Controllers\Public;

use App\Models\Author;
use App\Models\Category;
use Illuminate\Http\Response;

class SitemapController extends PublicController
{
    public function __invoke(): Response
    {
        $articles = $this->publishedArticles()
            ->latest('updated_at')
            ->limit(50000)
            ->get(['id', 'slug', 'updated_at', 'published_at']);

        $authors = Author::query()
            ->where('is_active', true)
            ->whereHas('articles', fn ($query) => $query->published())
            ->latest('updated_at')
            ->get(['id', 'slug', 'updated_at']);

        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas('articles', fn ($query) => $query->published())
            ->latest('updated_at')
            ->get(['id', 'slug', 'updated_at']);

        $sitemapBaseUrl = config('publication.sitemap_base_url');

        return response()
            ->view('public.sitemap', compact('articles', 'authors', 'categories', 'sitemapBaseUrl'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
