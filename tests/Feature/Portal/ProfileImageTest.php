<?php

namespace Tests\Feature\Portal;

use App\Enums\CommentStatus;
use App\Models\Article;
use App\Models\Author;
use App\Models\Comment;
use App\Models\EditorialMember;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfileImageTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['status' => 'active', 'is_active' => true]);
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $user;
    }

    public function test_author_photo_is_saved_to_account_and_public_profile_and_retained_without_replacement(): void
    {
        Storage::fake('public');
        $user = $this->member('author');
        Author::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user)->get(route('author.profile.edit'))->assertOk()->assertSee('data-profile-image-input', false);
        $fields = ['name' => $user->name, 'email' => $user->email];
        $this->put(route('author.profile.update'), $fields + ['avatar' => UploadedFile::fake()->image('portrait.png')])->assertSessionHasNoErrors()->assertRedirect();
        $path = $user->fresh()->profile_image_path;
        Storage::disk('public')->assertExists($path);
        $this->assertSame($path, $user->author()->first()->avatar_path);
        $this->get(route('author.profile.edit'))->assertSee(Storage::disk('public')->url($path));
        $this->put(route('author.profile.update'), $fields)->assertSessionHasNoErrors();
        $this->assertSame($path, $user->fresh()->profile_image_path);
        $this->assertSame($path, $user->author()->first()->avatar_path);
    }

    public function test_editor_can_upload_a_photo_only_to_their_own_account(): void
    {
        Storage::fake('public');
        $user = $this->member('editor');
        $other = User::factory()->create();
        $this->actingAs($user)->get(route('editor.profile.edit'))->assertOk()->assertSee('data-profile-image-input', false);
        $this->put(route('editor.profile.update'), ['name' => $user->name, 'avatar' => UploadedFile::fake()->image('editor.jpg'), 'user_id' => $other->id])->assertSessionHasNoErrors()->assertRedirect();
        $path = $user->fresh()->profile_image_path;
        Storage::disk('public')->assertExists($path);
        $this->get(route('editor.profile.edit'))->assertSee(Storage::disk('public')->url($path));
        $this->put(route('editor.profile.update'), ['name' => $user->name])->assertSessionHasNoErrors();
        $this->assertSame($path, $user->fresh()->profile_image_path);
        $this->assertNull($other->fresh()->profile_image_path);
    }

    public function test_invalid_photo_keeps_the_existing_photo_and_non_editors_cannot_update_editor_profiles(): void
    {
        Storage::fake('public');
        $editor = $this->member('editor');
        $editor->update(['profile_image_path' => 'editors/avatars/original.jpg']);
        $this->actingAs($editor)->put(route('editor.profile.update'), ['name' => 'Changed', 'avatar' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')])->assertSessionHasErrors('avatar');
        $this->assertSame('editors/avatars/original.jpg', $editor->fresh()->profile_image_path);
        $this->assertNotSame('Changed', $editor->fresh()->name);
        $author = $this->member('author');
        $this->actingAs($author)->put(route('editor.profile.update'), ['name' => 'Changed'])->assertForbidden();
        $this->get(route('editor.profile.edit'))->assertForbidden();
    }

    public static function publicRoles(): array
    {
        return array_map(fn ($role) => [$role], ['author', 'contributor', 'editor', 'reviewer', 'admin', 'super-admin']);
    }

    #[DataProvider('publicRoles')]
    public function test_every_role_can_publish_replace_and_retain_its_own_photo_without_exposing_private_account_fields(string $role): void
    {
        Storage::fake('public');
        $user = $this->member($role);
        $linkedAuthor = Author::factory()->create(['user_id' => $user->id]);
        $user->update(['phone' => '+919876543210', 'reviewer_profile' => ['expertise' => 'Confidential reviewer expertise']]);
        $other = User::factory()->create();
        $portal = match ($role) {
            'contributor' => 'author',
            'super-admin' => 'admin',
            default => $role,
        };
        $edit = $portal === 'admin' ? 'admin.account.edit' : $portal.'.profile.edit';
        $update = $portal === 'admin' ? 'admin.account.profile.update' : $portal.'.profile.update';
        $originalPassword = $user->password;
        $fields = ['name' => $user->name, 'email' => $user->email, 'phone' => '+919876543210', 'organization' => 'Public institution', 'designation' => 'Public designation', 'user_id' => $other->id, 'status' => 'rejected', 'password' => 'MustNotChange'];
        $this->actingAs($user)->get(route($edit))->assertOk()->assertSee('data-profile-image-input', false)->assertSee('public profile');
        $this->put(route($update), $fields + ['avatar' => UploadedFile::fake()->image('portrait.jpg', 600, 400)])->assertRedirect()->assertSessionHasNoErrors();
        $first = $user->fresh()->profile_image_path;
        Storage::disk('public')->assertExists($first);
        $this->assertNull($other->fresh()->profile_image_path);
        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertTrue($user->fresh()->isActive());
        $this->actingAs($user->fresh())->get(route($edit))->assertSee(route('people.show', $user))->assertSee(Storage::disk('public')->url($first));
        $this->put(route($update), $fields + ['avatar' => UploadedFile::fake()->image('replacement.png', 400, 400)])->assertSessionHasNoErrors();
        $replacement = $user->fresh()->profile_image_path;
        $this->assertNotSame($first, $replacement);
        Storage::disk('public')->assertExists($replacement);
        $this->put(route($update), $fields)->assertSessionHasNoErrors();
        $this->assertSame($replacement, $user->fresh()->profile_image_path);
        $this->assertSame($replacement, $linkedAuthor->fresh()->avatar_path);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get(route('people.show', $user))->assertOk()->assertSee(Storage::disk('public')->url($replacement))
            ->assertSee('Public institution')->assertSee('Public designation')->assertDontSee($user->email)
            ->assertDontSee('+919876543210')->assertDontSee('Confidential reviewer expertise')->assertDontSee($originalPassword);
        $this->get(route('people.index'))->assertOk()->assertSee(route('people.show', $user))->assertSee(Storage::disk('public')->url($replacement))->assertDontSee($user->email);
        if ($role === 'editor') {
            $this->get(route('editorial-board'))->assertOk()->assertSee(Storage::disk('public')->url($replacement))->assertSee(route('people.show', $user));
        }
        if (in_array($role, ['author', 'contributor'])) {
            $author = $user->author()->firstOrFail();
            $this->assertSame($replacement, $author->avatar_path);
            $this->get(route('authors.show', $author))->assertOk()->assertSee(Storage::disk('public')->url($replacement));
            $article = Article::factory()->published()->create();
            $article->authors()->attach($author);
            $this->get(route('articles.show', $article))->assertOk()->assertSee(Storage::disk('public')->url($replacement));
            $this->get(route('people.show', $user))->assertSee($article->title);
        }
    }

    public function test_pending_unverified_suspended_and_photoless_users_have_no_public_profile_and_cannot_upload(): void
    {
        $this->seed(RolePermissionSeeder::class);
        foreach ([['status' => 'pending', 'is_active' => false], ['email_verified_at' => null], ['status' => 'suspended'], ['is_active' => false], ['profile_image_path' => null]] as $overrides) {
            $user = User::factory()->create($overrides + ['profile_image_path' => 'users/avatars/hidden.jpg']);
            $user->roles()->attach(Role::where('slug', 'reviewer')->firstOrFail());
            $this->get(route('people.show', $user))->assertNotFound();
            $this->get(route('people.index'))->assertDontSee(route('people.show', $user));
            if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
                $this->flushSession();
                $this->actingAs($user)->put(route('reviewer.profile.update'), ['name' => 'Unauthorized', 'avatar' => UploadedFile::fake()->image('hidden.png')])->assertRedirect();
                $this->assertNotSame('Unauthorized', $user->fresh()->name);
                $this->app['auth']->forgetGuards();
                $this->flushSession();
            }
        }
    }

    public function test_reviewer_and_admin_reject_invalid_images_and_do_not_allow_cross_role_updates(): void
    {
        Storage::fake('public');
        foreach (['reviewer' => 'reviewer.profile.update', 'admin' => 'admin.account.profile.update'] as $role => $route) {
            $this->flushSession();
            $user = $this->member($role);
            $user->update(['profile_image_path' => 'users/avatars/original.jpg']);
            $this->actingAs($user)->put(route($route), ['name' => 'Changed', 'avatar' => UploadedFile::fake()->create('unsafe.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('avatar');
            $this->assertSame('users/avatars/original.jpg', $user->fresh()->profile_image_path);
            $this->assertNotSame('Changed', $user->fresh()->name);
        }
        $this->flushSession();
        $author = $this->member('author');
        $this->actingAs($author)->put(route('admin.account.profile.update'), ['name' => 'Forbidden'])->assertForbidden();
        $this->put(route('reviewer.profile.update'), ['name' => 'Forbidden'])->assertForbidden();
    }

    public function test_public_comments_and_replies_show_approved_members_photos_without_revealing_contact_details(): void
    {
        config(['publication.features.comments' => true]);
        $user = $this->member('reviewer');
        $user->update(['profile_image_path' => 'reviewers/avatars/public.jpg']);
        $article = Article::factory()->published()->create(['comments_enabled' => true]);
        $comment = Comment::create(['article_id' => $article->id, 'user_id' => $user->id, 'body' => 'A public comment.', 'status' => CommentStatus::Approved, 'approved_at' => now()]);
        Comment::create(['article_id' => $article->id, 'parent_id' => $comment->id, 'user_id' => $user->id, 'body' => 'A public reply.', 'status' => CommentStatus::Approved, 'approved_at' => now()]);
        $url = $user->profile_image_url;
        $response = $this->get(route('articles.show', $article))->assertOk()->assertSee($url)->assertDontSee($user->email);
        $this->assertSame(2, substr_count($response->getContent(), $url));
        $user->update(['is_active' => false]);
        $this->get(route('articles.show', $article))->assertDontSee($url);
    }

    public function test_published_editorial_board_photos_display_for_editors_and_reviewers(): void
    {
        foreach (['editorial_board' => 'Editor', 'reviewers' => 'Reviewer'] as $group => $role) {
            EditorialMember::create(['name' => 'Published '.$role, 'group' => $group, 'role' => $role, 'photo_path' => 'editorial/'.$group.'.jpg', 'is_active' => true]);
        }
        EditorialMember::create(['name' => 'Hidden member', 'group' => 'editorial_board', 'role' => 'Editor', 'photo_path' => 'editorial/hidden.jpg', 'is_active' => false]);
        $this->get(route('editorial-board'))->assertOk()
            ->assertSee(Storage::disk('public')->url('editorial/editorial_board.jpg'))
            ->assertSee(Storage::disk('public')->url('editorial/reviewers.jpg'))
            ->assertDontSee('editorial/hidden.jpg');
    }
}
