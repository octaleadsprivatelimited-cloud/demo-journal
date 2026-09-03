<?php

namespace App\Http\Controllers\Public;

use App\Models\Author;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuthorController extends PublicController
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));

        $authors = Author::query()
            ->where('is_active', true)
            ->when($term, function ($query, $term): void {
                $escaped = addcslashes($term, '%_');
                $query->where(function ($builder) use ($escaped): void {
                    $builder->where('name', 'like', "%{$escaped}%")
                        ->orWhere('organization', 'like', "%{$escaped}%")
                        ->orWhere('designation', 'like', "%{$escaped}%");
                });
            })
            ->whereHas('articles', fn ($query) => $query->published())
            ->withCount(['articles as published_articles_count' => fn ($query) => $query->published()])
            ->orderByDesc('is_verified')
            ->orderBy('name')
            ->paginate(18)
            ->withQueryString();

        return view('public.authors.index', $this->publicViewData([
            'authors' => $authors,
            'term' => $term,
        ]));
    }

    public function show(string $slug): View
    {
        $author = Author::query()
            ->where('is_active', true)
            ->where('slug', $slug)
            ->withCount(['articles as published_articles_count' => fn ($query) => $query->published()])
            ->firstOrFail();

        $articles = $this->publishedArticles()
            ->whereHas('authors', fn ($query) => $query->whereKey($author->getKey()))
            ->latest('published_at')
            ->paginate(10);

        return view('public.authors.show', $this->publicViewData(compact('author', 'articles')));
    }
}
