<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reviewer;

use App\Enums\ReviewRecommendation;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\CompleteReviewRequest;
use App\Models\Review;
use App\Services\ArticleWorkflowService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReviewController extends Controller
{
    public function show(Review $review): View
    {
        Gate::authorize('view', $review);

        if ($review->status === ReviewStatus::Assigned) {
            $review->forceFill(['status' => ReviewStatus::InProgress, 'started_at' => now()])->save();
        }

        $review->load(['article.category:id,name,slug', 'article.tags:id,name,slug', 'article.authors:id,name,slug,organization', 'submission.version', 'comments.user:id,name']);

        return view('reviewer.reviews.show', compact('review'));
    }

    public function update(CompleteReviewRequest $request, Review $review, ArticleWorkflowService $workflow): RedirectResponse
    {
        Gate::authorize('complete', $review);

        try {
            DB::transaction(function () use ($request, $review, $workflow): void {
                $completed = $workflow->completeReview(
                    $review,
                    $request->user(),
                    $request->enum('recommendation', ReviewRecommendation::class),
                    $request->string('comments_to_author')->toString(),
                    $request->string('confidential_comments')->trim()->toString() ?: null,
                );

                foreach ($request->input('comments', []) as $comment) {
                    $completed->comments()->create([
                        'user_id' => $request->user()->getKey(),
                        'body' => $comment['body'],
                        'location' => $comment['location'] ?? null,
                        'is_confidential' => false,
                    ]);
                }
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['recommendation' => $exception->getMessage()]);
        }

        return redirect()->route('reviewer.reviews.show', $review)->with('success', 'Your recommendation is recorded and locked.');
    }

    public function download(Review $review): StreamedResponse
    {
        Gate::authorize('view', $review);
        $article = $review->article;
        abort_unless($article->pdf_path && Storage::disk('local')->exists($article->pdf_path), 404);

        return Storage::disk('local')->download($article->pdf_path, str($article->title)->slug().'-manuscript.pdf');
    }
}
