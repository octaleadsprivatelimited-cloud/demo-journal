<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Author;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthorizationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_author_bypass_is_scoped_and_leaves_no_active_identity(): void
    {
        $this->seed(RolePermissionSeeder::class);
        config()->set('security.local_author_bypass.enabled', true);
        config()->set('security.local_admin_bypass.enabled', false);
        config()->set('security.local_admin_bypass.bind_address', '127.0.0.1');
        config()->set('security.local_admin_bypass.allowed_remote_addresses', ['127.0.0.1']);
        $this->get('http://localhost/author/dashboard')->assertRedirect(route('author.login'));
        $this->app->detectEnvironment(static fn (): string => 'local');
        $this->get('http://localhost/author/dashboard')->assertOk();
        $this->get('http://localhost/author/articles/create')->assertOk();
        $user = User::where('email', 'local-author@localhost.test')->firstOrFail();
        $this->assertFalse($user->isActive());
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame(0, $user->roles()->count());
        $this->assertGuest();
        $this->get('http://localhost/admin')->assertRedirect(route('admin.login'));
        $this->get('http://example.test/author/dashboard')->assertRedirect(route('author.login'));
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
            ->get('http://localhost/author/dashboard')->assertRedirect(route('author.login'));
    }

    public function test_editor_and_reviewer_bypasses_are_local_and_request_scoped(): void
    {
        $this->seed(RolePermissionSeeder::class);
        config()->set('security.local_admin_bypass.enabled', false);
        config()->set('security.local_admin_bypass.bind_address', '127.0.0.1');
        config()->set('security.local_admin_bypass.allowed_remote_addresses', ['127.0.0.1']);
        foreach (['editor', 'reviewer'] as $portal) {
            config()->set('security.local_'.$portal.'_bypass.enabled', true);
            $this->app->detectEnvironment(static fn (): string => 'testing');
            $this->get('http://localhost/'.$portal.'/dashboard')->assertRedirect();
            $this->app->detectEnvironment(static fn (): string => 'local');
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
                ->get('http://localhost/'.$portal.'/dashboard')->assertOk();
            $user = User::where('email', 'local-'.$portal.'@localhost.test')->firstOrFail();
            $this->assertFalse($user->isActive());
            $this->assertFalse($user->hasVerifiedEmail());
            $this->assertSame(0, $user->roles()->count());
            $this->assertGuest();
            $this->get('http://example.test/'.$portal.'/dashboard')->assertRedirect();
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.1'])
                ->get('http://localhost/'.$portal.'/dashboard')->assertRedirect();
            config()->set('security.local_'.$portal.'_bypass.enabled', false);
        }
        $this->get('http://localhost/admin')->assertRedirect();
    }

    public function test_switching_trusted_local_portals_never_persists_the_temporary_password_hash(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->app->detectEnvironment(static fn (): string => 'local');
        config(['security.local_admin_bypass.bind_address' => '127.0.0.1', 'security.local_admin_bypass.allowed_remote_addresses' => ['127.0.0.1']]);
        foreach (['author', 'editor', 'reviewer', 'admin'] as $portal) {
            config(['security.local_'.$portal.'_bypass.enabled' => true]);
            $path = $portal === 'admin' ? '/admin' : '/'.$portal.'/dashboard';
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->get('http://localhost'.$path)
                ->assertOk()->assertSessionMissing('password_hash_web');
            $this->assertGuest();
        }
    }

    public function test_local_temporary_identity_preserves_the_prior_real_sessions_password_hash(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', 'author')->firstOrFail());
        $this->app->detectEnvironment(static fn (): string => 'local');
        config(['security.local_editor_bypass.enabled' => true, 'security.local_author_bypass.enabled' => false,
            'security.local_admin_bypass.bind_address' => '127.0.0.1', 'security.local_admin_bypass.allowed_remote_addresses' => ['127.0.0.1']]);
        $hash = $this->app['auth']->guard('web')->hashPasswordForCookie($user->getAuthPassword());
        $this->actingAs($user)->withSession(['password_hash_web' => $hash]);
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->get('http://localhost/editor/dashboard')
            ->assertOk()->assertSessionHas('password_hash_web', $hash);
        $this->assertAuthenticatedAs($user);
        $this->get('http://localhost/author/dashboard')->assertOk();
        $this->withSession(['password_hash_web' => 'a-stale-real-session-hash']);
        $this->get('http://localhost/editor/dashboard')->assertOk()->assertSessionHas('password_hash_web', 'a-stale-real-session-hash');
        $this->getJson('http://localhost/author/dashboard')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_local_portal_identity_carries_into_shared_workflow_only_locally(): void
    {
        config()->set('security.local_admin_bypass.enabled', true);
        config()->set('security.local_admin_bypass.bind_address', '127.0.0.1');
        config()->set('security.local_admin_bypass.allowed_remote_addresses', ['127.0.0.1']);
        $this->app->detectEnvironment(static fn (): string => 'local');
        $this->get('http://localhost/admin')->assertOk();
        $this->get('http://localhost/workflow')->assertOk();
        $this->assertGuest();
        $this->get('http://example.test/workflow')->assertRedirect();
        config()->set('security.local_admin_bypass.enabled', false);
        $this->get('http://localhost/workflow')->assertRedirect();
    }

    public function test_guests_are_redirected_from_every_protected_workspace(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/author/dashboard')->assertRedirect(route('author.login'));
        $this->get('/editor/dashboard')->assertRedirect(route('editor.login'));
        $this->get('/reviewer/dashboard')->assertRedirect(route('reviewer.login'));
    }

    public function test_localhost_admin_bypass_requires_every_safety_condition(): void
    {
        config()->set('security.local_admin_bypass', [
            'enabled' => true,
            'name' => 'Local Administrator',
            'email' => 'local-admin@localhost.test',
            'bind_address' => '127.0.0.1',
            'allowed_remote_addresses' => ['127.0.0.1', '::1'],
        ]);

        $this->get('/admin')->assertRedirect(route('admin.login'));

        $this->app->detectEnvironment(static fn (): string => 'local');
        $this->get('http://journal.example.test/admin')->assertRedirect(route('admin.login'));

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->get('http://localhost/admin')
            ->assertRedirect(route('admin.login'));

        config()->set('security.local_admin_bypass.bind_address', '0.0.0.0');
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('http://localhost/admin')
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_localhost_admin_bypass_uses_a_non_reusable_request_scoped_identity(): void
    {
        config()->set('security.local_admin_bypass', [
            'enabled' => true,
            'name' => 'Local Administrator',
            'email' => 'local-admin@localhost.test',
            'bind_address' => '127.0.0.1',
            'allowed_remote_addresses' => ['127.0.0.1', '::1'],
        ]);
        $this->app->detectEnvironment(static fn (): string => 'local');

        $this->get('http://localhost/admin')->assertOk();

        $user = User::query()->where('email', 'local-admin@localhost.test')->firstOrFail();
        $this->assertGuest();
        $this->assertTrue($user->is_local_admin_bypass);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertFalse($user->isActive());
        $this->assertFalse($user->hasRole('super-admin'));

        config()->set('security.local_admin_bypass.enabled', false);
        $this->get('http://localhost/admin')->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_localhost_admin_bypass_refuses_an_existing_email_collision(): void
    {
        $existingUser = User::factory()->create(['email' => 'local-admin@localhost.test']);

        config()->set('security.local_admin_bypass', [
            'enabled' => true,
            'name' => 'Local Administrator',
            'email' => $existingUser->email,
            'bind_address' => '127.0.0.1',
            'allowed_remote_addresses' => ['127.0.0.1', '::1'],
        ]);
        $this->app->detectEnvironment(static fn (): string => 'local');

        $this->get('http://localhost/admin')->assertRedirect(route('admin.login'));

        $existingUser->refresh();
        $this->assertGuest();
        $this->assertFalse($existingUser->is_local_admin_bypass);
        $this->assertFalse($existingUser->hasRole('super-admin'));
    }

    public function test_localhost_admin_bypass_revokes_a_persisted_identity_outside_valid_conditions(): void
    {
        $role = Role::query()->create(['name' => 'Super Admin', 'slug' => 'super-admin']);
        $user = User::factory()->create();
        $user->forceFill(['is_local_admin_bypass' => true])->save();
        $user->roles()->attach($role);

        config()->set('security.local_admin_bypass.enabled', false);

        $this->actingAs($user)->get('/admin')->assertRedirect(route('admin.login'));

        $user->refresh();
        $this->assertGuest();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertFalse($user->isActive());
        $this->assertFalse($user->hasRole('super-admin'));
    }

    public function test_author_role_cannot_enter_admin_workspace(): void
    {
        $role = Role::query()->create(['name' => 'Author', 'slug' => 'author']);
        $user = User::factory()->create();
        $user->roles()->attach($role);
        Author::factory()->create(['user_id' => $user->getKey(), 'email' => $user->email]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_article_policy_blocks_cross_author_mutation(): void
    {
        $role = Role::query()->create(['name' => 'Author', 'slug' => 'author']);
        $permissions = collect(['articles.update', 'articles.submit'])->map(fn (string $slug) => Permission::query()->create([
            'name' => str($slug)->replace('.', ' ')->headline(),
            'slug' => $slug,
            'group' => 'articles',
        ]));
        $role->permissions()->attach($permissions->pluck('id'));
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $owner->roles()->attach($role);
        $other->roles()->attach($role);
        Author::factory()->create(['user_id' => $owner->getKey(), 'email' => $owner->email]);
        Author::factory()->create(['user_id' => $other->getKey(), 'email' => $other->email]);
        $article = Article::factory()->draft()->create(['created_by_id' => $owner->getKey()]);

        $this->assertFalse($other->can('update', $article));
        $this->assertFalse($other->can('submit', $article));
        $this->assertTrue($owner->can('update', $article));
    }
}
