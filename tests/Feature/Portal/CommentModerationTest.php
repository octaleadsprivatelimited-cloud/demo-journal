<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Enums\CommentStatus;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CommentModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authorized_editor_can_approve_a_pending_comment(): void
    {
        $editor = $this->moderator();
        $article = Article::factory()->published()->create();
        $comment = Comment::query()->create([
            'article_id' => $article->getKey(),
            'guest_name' => 'Thoughtful Reader',
            'guest_email' => 'reader@example.test',
            'body' => 'This contribution is awaiting an editorial decision.',
            'status' => CommentStatus::Pending,
        ]);

        $this->actingAs($editor)
            ->patch(route('admin.comments.update', $comment), ['status' => CommentStatus::Approved->value])
            ->assertRedirect();

        $comment->refresh();
        $this->assertSame(CommentStatus::Approved, $comment->status);
        $this->assertNotNull($comment->approved_at);

        $this->get(route('articles.show', $article->slug))
            ->assertOk()
            ->assertSee('This contribution is awaiting an editorial decision.');
    }

    public function test_an_admin_without_the_moderation_permission_is_forbidden(): void
    {
        $role = Role::query()->create(['name' => 'Admin', 'slug' => 'admin']);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)->get(route('admin.comments.index'))->assertForbidden();
    }

    private function moderator(): User
    {
        $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
        $permission = Permission::query()->create([
            'name' => 'Moderate comments',
            'slug' => 'comments.moderate',
            'group' => 'comments',
        ]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
