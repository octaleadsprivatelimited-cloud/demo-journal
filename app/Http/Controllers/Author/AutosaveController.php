<?php

declare(strict_types=1);

namespace App\Http\Controllers\Author;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Author\AutosaveArticleRequest;
use App\Models\Article;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class AutosaveController extends Controller
{
    public function __invoke(AutosaveArticleRequest $request, Article $article): JsonResponse
    {
        return DB::transaction(function () use ($request, $article): JsonResponse {
            $article = Article::query()->lockForUpdate()->findOrFail($article->getKey());
            Gate::authorize('update', $article);
            abort_unless(in_array($article->status, [ArticleStatus::Draft, ArticleStatus::RevisionRequired, ArticleStatus::Rejected], true)
                && (! $article->workflow || in_array($article->workflow->stage, ['draft', 'returned', 'minor_revision', 'major_revision'], true)), 409, 'This manuscript is locked.');

            $clientVersion = $request->string('draft_version')->toString();
            $staleTimestamp = $request->filled('updated_at')
                && ! $article->updated_at->equalTo(CarbonImmutable::parse($request->string('updated_at')->toString()));

            if ($clientVersion === '' || ! hash_equals($article->draftVersion(), $clientVersion) || $staleTimestamp) {
                return response()->json([
                    'message' => 'This draft has changed or its version is missing. Reload before saving to avoid overwriting it.',
                    'code' => 'version_conflict',
                    'updated_at' => $article->updated_at->toISOString(),
                    'draft_version' => $article->draftVersion(),
                ], 409);
            }

            $data = $request->safe()->except(['updated_at', 'draft_version']);
            if (array_key_exists('content', $data)) {
                $data['reading_time_minutes'] = max(1, (int) ceil(str_word_count(strip_tags((string) $data['content'])) / 220));
            }
            $article->fill($data)->save();
            $article = $article->fresh();

            return response()->json([
                'message' => 'Draft saved',
                'updated_at' => $article->updated_at->toISOString(),
                'draft_version' => $article->draftVersion(),
            ]);
        });
    }
}
