<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ArticleStatus;
use App\Enums\SubmissionStatus;
use App\Events\ArticleStatusChanged;
use App\Events\ArticleSubmitted;
use App\Events\ReviewAssigned;
use App\Jobs\PublishScheduledArticles;
use App\Models\Article;
use App\Models\Role;
use App\Models\User;
use App\Services\ArticleWorkflowService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ArticleWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_is_atomic_and_captures_an_immutable_version(): void
    {
        Event::fake([ArticleStatusChanged::class, ArticleSubmitted::class]);
        $author = User::factory()->create();
        $article = Article::factory()->draft()->create(['created_by_id' => $author->getKey()]);

        $submission = app(ArticleWorkflowService::class)->submit($article, $author, 'Please review this research.');

        $this->assertSame(SubmissionStatus::Pending, $submission->status);
        $this->assertSame(ArticleStatus::Submitted, $article->refresh()->status);
        $this->assertSame(1, $article->versions()->count());
        $this->assertSame($article->content, $article->versions()->firstOrFail()->content);
        Event::assertDispatched(ArticleSubmitted::class);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        Event::fake([ArticleStatusChanged::class, ArticleSubmitted::class, ReviewAssigned::class]);
        $article = Article::factory()->draft()->create();

        $this->expectException(DomainException::class);
        app(ArticleWorkflowService::class)->transition($article, ArticleStatus::Published);
    }

    public function test_reviewer_assignment_moves_submission_into_review(): void
    {
        Event::fake([ArticleStatusChanged::class, ReviewAssigned::class]);
        $reviewer = User::factory()->create();
        $role = Role::query()->create(['name' => 'Reviewer', 'slug' => 'reviewer']);
        $reviewer->roles()->attach($role, ['assigned_at' => now()]);
        $article = Article::factory()->create(['status' => ArticleStatus::Submitted]);

        $review = app(ArticleWorkflowService::class)->assignReviewer($article, $reviewer);

        $this->assertSame($reviewer->getKey(), $review->reviewer_id);
        $this->assertSame(ArticleStatus::UnderReview, $article->refresh()->status);
        Event::assertDispatched(ReviewAssigned::class);
    }

    public function test_scheduled_publication_job_publishes_due_articles(): void
    {
        Event::fake([ArticleStatusChanged::class]);
        $article = Article::factory()->create([
            'status' => ArticleStatus::Scheduled,
            'scheduled_for' => now()->subMinute(),
            'published_at' => null,
        ]);

        app(PublishScheduledArticles::class)->handle(app(ArticleWorkflowService::class));

        $this->assertSame(ArticleStatus::Published, $article->refresh()->status);
        $this->assertNotNull($article->published_at);
    }
}
