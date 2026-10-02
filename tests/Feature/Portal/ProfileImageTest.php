<?php

namespace Tests\Feature\Portal;

use App\Models\{Author, Role, User};
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
}
