<?php

declare(strict_types=1);

namespace App\Http\Controllers\Author;

use App\Enums\ArticleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Author\AutosaveArticleRequest;
use App\Models\Article;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

final class AutosaveController extends Controller
{
    public function __invoke(AutosaveArticleRequest $request, Article $article): JsonResponse
    {
        Gate::authorize('update', $article);
        abort_unless(in_array($article->status, [ArticleStatus::Draft, ArticleStatus::RevisionRequired, ArticleStatus::Rejected], true), 409, 'This manuscript is locked.');

        if ($request->filled('updated_at')) {
            $clientTimestamp = CarbonImmutable::parse($request->string('updated_at')->toString());
            if (! $article->updated_at->equalTo($clientTimestamp)) {
                return response()->json([
                    'message' => 'A newer copy exists. Reload before saving to avoid overwriting it.',
                    'code' => 'version_conflict',
                    'updated_at' => $article->updated_at->toISOString(),
                ], 409);
            }
        }

        $data = $request->safe()->except(['updated_at']);
        if (array_key_exists('content', $data)) {
            $data['reading_time_minutes'] = max(1, (int) ceil(str_word_count(strip_tags((string) $data['content'])) / 220));
        }
        $article->fill($data)->save();

        return response()->json([
            'message' => 'Draft saved',
            'updated_at' => $article->fresh()->updated_at->toISOString(),
        ]);
    }
}
