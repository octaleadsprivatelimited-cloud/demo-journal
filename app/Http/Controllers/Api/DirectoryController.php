<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Author;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

final class DirectoryController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->active()
            ->withCount(['articles' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug', 'description']);

        return response()->json(['data' => $categories]);
    }

    public function author(Author $author): JsonResponse
    {
        abort_unless($author->is_active, 404);

        $articles = $author->articles()
            ->published()
            ->with(['category', 'authors', 'tags'])
            ->latest('published_at')
            ->paginate(15);

        return response()->json([
            'data' => [
                'name' => $author->name,
                'slug' => $author->slug,
                'biography' => $author->biography,
                'designation' => $author->designation,
                'organization' => $author->organization,
                'website_url' => $author->website_url,
                'social_links' => $author->social_links ?? [],
                'articles' => ArticleResource::collection($articles)->response()->getData(true),
            ],
        ]);
    }
}
