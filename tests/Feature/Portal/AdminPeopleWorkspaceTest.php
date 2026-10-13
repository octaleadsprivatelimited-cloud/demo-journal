<?php

namespace Tests\Feature\Portal;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPeopleWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_shortcuts_select_only_editor_or_reviewer_and_use_the_existing_account_endpoint(): void
    {
        $this->signIn('super-admin');
        foreach (['editor', 'reviewer'] as $slug) {
            $role = Role::where('slug', $slug)->firstOrFail();
            $this->get(route('admin.users.create', ['role' => $slug]))->assertOk()
                ->assertViewHas('staffRole', fn ($selected) => $selected->is($role))
                ->assertSee('Create '.$slug.' account')
                ->assertSee('name="email_verified" value="0"', false)
                ->assertSee('name="roles[]" value="'.$role->id.'"', false)
                ->assertDontSee('Email identity is verified');
        }
        $this->get(route('admin.users.create', ['role' => 'super-admin']))->assertOk()->assertViewHas('staffRole', null);
    }

    public function test_pending_applications_have_their_own_view_and_existing_accounts_stay_separate(): void
    {
        $this->signIn('super-admin');
        User::factory()->create(['name' => 'Pending editorial applicant', 'status' => 'pending', 'requested_role' => 'editor', 'is_active' => false]);
        User::factory()->create(['name' => 'Existing journal member', 'status' => 'active', 'requested_role' => null]);
        $this->get(route('admin.users.index'))->assertOk()->assertViewHas('tab', 'accounts')
            ->assertSee('Account approvals')->assertSee('Existing journal member')->assertDontSee('Pending editorial applicant');
        $this->get(route('admin.users.index', ['tab' => 'applications']))->assertOk()->assertViewHas('tab', 'applications')
            ->assertSee('Pending editorial applicant')->assertSee('Approve')->assertDontSee('Existing journal member');
    }

    public function test_normal_admin_navigation_does_not_expose_staff_creation_or_account_approval(): void
    {
        $this->signIn('admin');
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Manuscripts')->assertSee('More tools')
            ->assertDontSee('Add editor')->assertDontSee('Add reviewer')->assertDontSee('Account approvals');
        $this->get(route('admin.users.create', ['role' => 'editor']))->assertForbidden();
        $this->get(route('admin.users.create', ['role' => 'reviewer']))->assertForbidden();
        $this->get(route('admin.users.index', ['tab' => 'applications']))->assertForbidden();
    }

    private function signIn(string $slug): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['status' => 'active', 'is_active' => true, 'email_verified_at' => now()]);
        $user->roles()->attach(Role::where('slug', $slug)->firstOrFail());
        $this->actingAs($user);
    }
}
