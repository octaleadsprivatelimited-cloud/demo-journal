<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Jobs\SendNewsletterCampaign;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class PortalSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'Author', 'slug' => 'author', 'is_system' => true]);
        $user->roles()->attach($role);
        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_cannot_assign_super_admin_role(): void
    {
        $admin = $this->roleUser('admin');
        $managed = User::factory()->create();
        $superRole = Role::query()->firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin', 'is_system' => true]);
        $this->actingAs($admin)->put(route('admin.users.update', $managed), [
            'name' => $managed->name, 'email' => $managed->email, 'roles' => [$superRole->id], 'is_active' => '1', 'email_verified' => '1',
        ])->assertForbidden();
        $this->assertFalse($managed->fresh()->roles()->where('slug', 'super-admin')->exists());
    }

    public function test_newsletter_campaign_is_persisted_and_queued(): void
    {
        Queue::fake();
        $admin = $this->roleUser('admin');
        $this->actingAs($admin)->post(route('admin.newsletter.campaigns.store'), [
            'subject' => 'Meridian Weekly', 'preview_text' => 'New research this week', 'content' => '<p>Read the new issue.</p>', 'action' => 'send',
        ])->assertRedirect(route('admin.newsletter.index'));
        $this->assertDatabaseHas('newsletter_campaigns', ['subject' => 'Meridian Weekly', 'status' => 'draft']);
        Queue::assertPushed(SendNewsletterCampaign::class);
    }

    private function roleUser(string $slug): User
    {
        $role = Role::query()->firstOrCreate(['slug' => $slug], ['name' => str($slug)->headline(), 'is_system' => true]);
        if ($slug === 'admin') {
            $permission = Permission::query()->firstOrCreate(['slug' => 'newsletter.manage'], ['name' => 'Manage newsletter', 'group' => 'newsletter']);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
