<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ArticleStatus;
use App\Enums\ReviewRecommendation;
use App\Enums\ReviewStatus;
use App\Enums\SubmissionStatus;
use App\Events\ArticleStatusChanged;
use App\Events\ArticleSubmitted;
use App\Events\ReviewAssigned;
use App\Events\ReviewCompleted;
use App\Models\Article;
use App\Models\Review;
use App\Models\Submission;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class ArticleWorkflowService
{
    public function __construct(private readonly ArticleVersionService $versions) {}

    public function submit(Article $article, User $actor, ?string $coverLetter = null): Submission
    {
        return DB::transaction(function () use ($article, $actor, $coverLetter): Submission {
            $article = Article::query()->lockForUpdate()->findOrFail($article->getKey());
            $from = $article->status;
            $this->assertTransition($from, ArticleStatus::Submitted);

            $version = $this->versions->snapshot($article, $actor, 'Submitted for editorial review');
            $round = ((int) $article->submissions()->max('round')) + 1;

            $submission = $article->submissions()->create([
                'article_version_id' => $version->getKey(),
                'submitted_by_id' => $actor->getKey(),
                'round' => $round,
                'status' => SubmissionStatus::Pending,
                'cover_letter' => $coverLetter,
                'submitted_at' => now(),
            ]);

            $article->forceFill([
                'status' => ArticleStatus::Submitted,
                'submitted_at' => now(),
                'rejected_at' => null,
            ])->save();

            ArticleStatusChanged::dispatch($article, $from, ArticleStatus::Submitted, $actor);
            ArticleSubmitted::dispatch($article, $submission);

            return $submission;
        });
    }

    public function transition(
        Article $article,
        ArticleStatus $to,
        ?User $actor = null,
        ?string $note = null,
        ?CarbonInterface $scheduledFor = null,
    ): Article {
        return DB::transaction(function () use ($article, $to, $actor, $note, $scheduledFor): Article {
            $article = Article::query()->lockForUpdate()->findOrFail($article->getKey());
            $from = $article->status;
            $this->assertTransition($from, $to);

            if ($to === ArticleStatus::Scheduled && (! $scheduledFor || ! $scheduledFor->isFuture())) {
                throw new DomainException('Scheduled articles require a publication time in the future.');
            }

            $attributes = ['status' => $to];
            $attributes += match ($to) {
                ArticleStatus::Approved => ['approved_at' => now(), 'rejected_at' => null],
                ArticleStatus::Rejected => ['rejected_at' => now()],
                ArticleStatus::Scheduled => ['scheduled_for' => $scheduledFor, 'approved_at' => $article->approved_at ?? now()],
                ArticleStatus::Published => ['published_at' => now(), 'scheduled_for' => null],
                default => [],
            };

            $article->forceFill($attributes)->save();
            $this->updateLatestSubmission($article, $to);
            ArticleStatusChanged::dispatch($article, $from, $to, $actor, $note);

            return $article->refresh();
        });
    }

    public function assignReviewer(
        Article $article,
        User $reviewer,
        ?User $assignedBy = null,
        ?CarbonInterface $dueAt = null,
    ): Review {
        return DB::transaction(function () use ($article, $reviewer, $assignedBy, $dueAt): Review {
            $article = Article::query()->lockForUpdate()->findOrFail($article->getKey());

            if (! in_array($article->status, [ArticleStatus::Submitted, ArticleStatus::UnderReview], true)) {
                throw new DomainException('A reviewer can only be assigned to a submitted article.');
            }

            if (! $reviewer->hasAnyRole('reviewer', 'editor', 'admin', 'super-admin')) {
                throw new DomainException('The selected user is not eligible to review articles.');
            }

            $submission = $article->submissions()->latest('round')->first();
            $review = $article->reviews()->create([
                'submission_id' => $submission?->getKey(),
                'reviewer_id' => $reviewer->getKey(),
                'assigned_by_id' => $assignedBy?->getKey(),
                'status' => ReviewStatus::Assigned,
                'due_at' => $dueAt,
            ]);

            if ($article->status === ArticleStatus::Submitted) {
                $from = $article->status;
                $article->forceFill(['status' => ArticleStatus::UnderReview])->save();
                $submission?->forceFill(['status' => SubmissionStatus::InReview])->save();
                ArticleStatusChanged::dispatch($article, $from, ArticleStatus::UnderReview, $assignedBy);
            }

            ReviewAssigned::dispatch($review);

            return $review;
        });
    }

    public function completeReview(
        Review $review,
        User $reviewer,
        ReviewRecommendation $recommendation,
        string $commentsToAuthor,
        ?string $confidentialComments = null,
    ): Review {
        return DB::transaction(function () use ($review, $reviewer, $recommendation, $commentsToAuthor, $confidentialComments): Review {
            $review = Review::query()->lockForUpdate()->findOrFail($review->getKey());

            if ($review->reviewer_id !== $reviewer->getKey()) {
                throw new DomainException('Only the assigned reviewer may complete this review.');
            }

            if (! in_array($review->status, [ReviewStatus::Assigned, ReviewStatus::InProgress], true)) {
                throw new DomainException('This review can no longer be completed.');
            }

            $review->forceFill([
                'status' => ReviewStatus::Completed,
                'recommendation' => $recommendation,
                'comments_to_author' => $commentsToAuthor,
                'confidential_comments' => $confidentialComments,
                'started_at' => $review->started_at ?? now(),
                'completed_at' => now(),
            ])->save();

            $review = $review->refresh();
            ReviewCompleted::dispatch($review);

            return $review;
        });
    }

    private function assertTransition(ArticleStatus $from, ArticleStatus $to): void
    {
        if (! $from->canTransitionTo($to)) {
            throw new DomainException("Article status cannot transition from {$from->value} to {$to->value}.");
        }
    }

    private function updateLatestSubmission(Article $article, ArticleStatus $status): void
    {
        $submission = $article->submissions()->latest('round')->first();

        if (! $submission) {
            return;
        }

        $submissionStatus = match ($status) {
            ArticleStatus::UnderReview => SubmissionStatus::InReview,
            ArticleStatus::RevisionRequired => SubmissionStatus::RevisionRequested,
            ArticleStatus::Approved, ArticleStatus::Production, ArticleStatus::Scheduled, ArticleStatus::Published => SubmissionStatus::Accepted,
            ArticleStatus::Rejected => SubmissionStatus::Rejected,
            default => null,
        };

        if ($submissionStatus) {
            $submission->forceFill([
                'status' => $submissionStatus,
                'decision_at' => in_array($submissionStatus, [SubmissionStatus::Accepted, SubmissionStatus::Rejected], true) ? now() : null,
            ])->save();
        }
    }
}
