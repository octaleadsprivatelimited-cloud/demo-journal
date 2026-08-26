<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ArticleSubmitted;
use App\Events\ReviewCompleted;
use App\Listeners\NotifyAuthorOfArticleSubmission;
use App\Listeners\NotifyStakeholdersOfCompletedReview;
use App\Models\Article;
use App\Models\Review;
use App\Models\Role;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\ReviewCompletedNotification;
use App\Notifications\SubmissionReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class WorkflowNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitter_receives_a_submission_receipt(): void
    {
        Notification::fake();
        $author = User::factory()->create();
        $article = Article::factory()->create(['created_by_id' => $author->id]);
        $submission = Submission::factory()->create(['article_id' => $article->id, 'submitted_by_id' => $author->id]);

        app(NotifyAuthorOfArticleSubmission::class)->handle(new ArticleSubmitted($article, $submission));

        Notification::assertSentTo($author, SubmissionReceivedNotification::class);
    }

    public function test_completed_review_notifies_the_author_and_editorial_team_without_exposing_review_text(): void
    {
        Notification::fake();
        $author = User::factory()->create();
        $editor = $this->roleUser('editor');
        $reviewer = User::factory()->create();
        $article = Article::factory()->create(['created_by_id' => $author->id]);
        $review = Review::factory()->create([
            'article_id' => $article->id,
            'reviewer_id' => $reviewer->id,
            'assigned_by_id' => $editor->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        app(NotifyStakeholdersOfCompletedReview::class)->handle(new ReviewCompleted($review));

        Notification::assertSentTo($author, ReviewCompletedNotification::class, fn (ReviewCompletedNotification $notification): bool => $notification->forAuthor);
        Notification::assertSentTo($editor, ReviewCompletedNotification::class, fn (ReviewCompletedNotification $notification): bool => ! $notification->forAuthor);
    }

    private function roleUser(string $slug): User
    {
        $role = Role::query()->firstOrCreate(['slug' => $slug], ['name' => str($slug)->headline(), 'is_system' => true]);
        $user = User::factory()->create();
        $user->roles()->attach($role, ['assigned_at' => now()]);

        return $user;
    }
}
