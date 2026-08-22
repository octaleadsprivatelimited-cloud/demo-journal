<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Services\Contracts\ArticleSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ArticleController extends Controller
{
    public function __construct(private readonly ArticleSearch $search) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:160'],
            'tag' => ['nullable', 'string', 'max:160'],
            'author' => ['nullable', 'string', 'max:160'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', 'in:newest,oldest,popular'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = (string) ($filters['q'] ?? '');
        unset($filters['q'], $filters['per_page']);

        return ArticleResource::collection(
            $this->search->search($query, $filters, (int) $request->integer('per_page', 15))
        );
    }

    public function show(Article $article): ArticleResource
    {
        abort_unless($article->status->value === 'published' && $article->published_at?->isPast(), 404);

        return new ArticleResource($article->load(['category', 'authors', 'tags']));
    }
}
