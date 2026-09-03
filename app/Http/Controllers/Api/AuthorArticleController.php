<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Services\ArticleWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class AuthorArticleController extends Controller
{
    public function __construct(private readonly ArticleWorkflowService $workflow) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $articles = Article::query()
            ->where('created_by_id', $request->user()->getKey())
            ->with(['category', 'authors', 'tags'])
            ->latest('updated_at')
            ->paginate(min(max($request->integer('per_page', 15), 1), 50));

        return ArticleResource::collection($articles);
    }

    public function store(StoreArticleRequest $request): ArticleResource
    {
        $article = DB::transaction(function () use ($request): Article {
            $data = Arr::except($request->validated(), ['tag_ids']);
            $data['created_by_id'] = $request->user()->getKey();
            $data['status'] = ArticleStatus::Draft;

            $article = Article::query()->create($data);

            if ($request->user()->author) {
                $article->authors()->attach($request->user()->author->getKey(), [
                    'is_corresponding' => true,
                    'sort_order' => 0,
                ]);
            }

            $article->tags()->sync($request->validated('tag_ids', []));

            return $article;
        });

        return new ArticleResource($article->load(['category', 'authors', 'tags']));
    }

    public function update(StoreArticleRequest $request, Article $article): ArticleResource
    {
        $this->authorize('update', $article);
        abort_unless(in_array($article->status, [ArticleStatus::Draft, ArticleStatus::RevisionRequired], true), 409, 'Only drafts and requested revisions can be edited.');

        DB::transaction(function () use ($article, $request): void {
            $article->update(Arr::except($request->validated(), ['tag_ids']));
            $article->tags()->sync($request->validated('tag_ids', []));
        });

        return new ArticleResource($article->refresh()->load(['category', 'authors', 'tags']));
    }

    public function submit(Request $request, Article $article): JsonResponse
    {
        $this->authorize('submit', $article);
        $request->validate(['cover_letter' => ['nullable', 'string', 'max:10000']]);

        $submission = $this->workflow->submit($article, $request->user(), $request->string('cover_letter')->toString() ?: null);

        return response()->json([
            'message' => 'Article submitted for editorial review.',
            'submission_id' => $submission->public_id,
            'status' => $submission->status->value,
        ], 201);
    }
}
