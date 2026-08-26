<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\ArticleStatus;
use App\Enums\ReviewRecommendation;
use App\Enums\ReviewStatus;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Permission;
use App\Models\Review;
use App\Models\Role;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ArchivedEditorialRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_and_review_keep_their_archived_article_relation(): void
    {
        [$article, $submission, $review] = $this->archivedEditorialRecord();

        $this->assertTrue($article->trashed());
        $this->assertTrue($submission->fresh()->load('article')->article->trashed());
        $this->assertTrue($review->fresh()->load('article')->article->trashed());
    }

    public function test_archived_editorial_history_renders_without_broken_article_links(): void
    {
        $author = $this->roleUser('author', ['articles.submit']);
        $reviewer = $this->roleUser('reviewer', ['articles.review']);
        $editor = $this->roleUser('editor', ['articles.review', 'articles.update-any', 'comments.moderate']);
        [$article, $submission, $review] = $this->archivedEditorialRecord($author, $reviewer);
        $submission->update(['submitted_by_id' => null]);
        $comment = Comment::query()->create([
            'article_id' => $article->getKey(),
            'guest_name' => 'Archive Reader',
            'guest_email' => 'archive-reader@example.test',
            'body' => 'A reader comment retained with the archived article.',
            'status' => 'pending',
        ]);
        $archivedArticleUrl = route('author.articles.show', $article);

        $this->actingAs($author)->get(route('author.submissions.index'))
            ->assertOk()
            ->assertSee($article->title)
            ->assertSee('Archived manuscript')
            ->assertDontSee($archivedArticleUrl, false);

        $this->actingAs($author)->get(route('author.reviews.index'))
            ->assertOk()
            ->assertSee($article->title)
            ->assertSee('Archived manuscript')
            ->assertDontSee($archivedArticleUrl, false);

        $this->actingAs($editor)->get(route('admin.submissions.index'))
            ->assertOk()
            ->assertSee($article->title)
            ->assertSee('Archived');
        $this->actingAs($editor)->get(route('editor.dashboard'))
            ->assertOk()
            ->assertSee($article->title)
            ->assertSee('Archived manuscript')
            ->assertSee('System');
        $this->actingAs($editor)->get(route('admin.submissions.show', $submission))
            ->assertOk()
            ->assertSee('Archived manuscript');
        $this->actingAs($editor)->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertSee($article->title)
            ->assertSee('Archived manuscript');
        $this->actingAs($editor)->get(route('admin.reviews.show', $review))
            ->assertOk()
            ->assertSee('Archived manuscript');
        $this->assertTrue($comment->fresh()->load('article')->article->trashed());
        $this->actingAs($editor)->get(route('admin.comments.index'))
            ->assertOk()
            ->assertSee($article->title)
            ->assertSee('Archived article')
            ->assertDontSee(route('articles.show', $article->slug), false);

        $this->actingAs($reviewer)->get(route('reviewer.dashboard'))
            ->assertOk()
            ->assertSee($article->title)
            ->assertSee('Archived manuscript');
        $this->actingAs($reviewer)->get(route('reviewer.reviews.show', $review))
            ->assertOk()
            ->assertSee($article->title)
            ->assertSee('Archived manuscript');
    }

    /** @return array{Article, Submission, Review} */
    private function archivedEditorialRecord(?User $author = null, ?User $reviewer = null): array
    {
        $author ??= User::factory()->create();
        $reviewer ??= User::factory()->create();
        $article = Article::factory()->create([
            'created_by_id' => $author->getKey(),
            'title' => 'Archived editorial record',
            'slug' => 'archived-editorial-record',
            'status' => ArticleStatus::Rejected,
            'rejected_at' => now(),
        ]);
        $submission = Submission::factory()->create([
            'article_id' => $article->getKey(),
            'submitted_by_id' => $author->getKey(),
        ]);
        $review = Review::factory()->create([
            'article_id' => $article->getKey(),
            'submission_id' => $submission->getKey(),
            'reviewer_id' => $reviewer->getKey(),
            'status' => ReviewStatus::Completed,
            'recommendation' => ReviewRecommendation::Reject,
            'comments_to_author' => 'The archived report remains available to the author.',
            'completed_at' => now(),
        ]);
        $article->delete();

        return [$article, $submission, $review];
    }

    /** @param list<string> $permissions */
    private function roleUser(string $slug, array $permissions): User
    {
        $role = Role::query()->create([
            'name' => str($slug)->headline(),
            'slug' => $slug,
            'is_system' => true,
        ]);

        foreach ($permissions as $permissionSlug) {
            $permission = Permission::query()->firstOrCreate(
                ['slug' => $permissionSlug],
                [
                    'name' => str($permissionSlug)->replace('.', ' ')->headline(),
                    'group' => str($permissionSlug)->before('.')->toString(),
                ],
            );
            $role->permissions()->attach($permission);
        }

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
