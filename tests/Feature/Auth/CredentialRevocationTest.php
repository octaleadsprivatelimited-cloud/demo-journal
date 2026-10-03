<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Services\CredentialRevocation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class CredentialRevocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response('', 200)]);
        Notification::fake();
    }

    public function test_revocation_preserves_only_the_authorized_current_session(): void
    {
        $user = $this->member('author');
        $other = $this->member('reviewer');
        $this->sessionRecord($user, 'preserved-current-session');
        $this->sessionRecord($user, 'revoked-other-session');
        $this->sessionRecord($other, 'unrelated-session');
        $user->createToken('prior-device', ['profile:read']);
        app(CredentialRevocation::class)->revoke($user, 'preserved-current-session');
        $this->assertDatabaseHas('sessions', ['id' => 'preserved-current-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'unrelated-session']);
        $this->assertDatabaseMissing('sessions', ['id' => 'revoked-other-session']);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_password_reset_revokes_tokens_and_all_prior_sessions(): void
    {
        $user = $this->member('author');
        $token = $user->createToken('compromised-device', ['profile:read'])->plainTextToken;
        $this->sessionRecord($user, 'reset-existing-session');
        $this->post(route('password.store'), [
            'email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'Fresh$Security123', 'password_confirmation' => 'Fresh$Security123',
        ])->assertSessionHasNoErrors()->assertRedirect(route('login'));
        $this->assertRecovered($user);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertRevokedToken($token);
    }

    public function test_author_password_change_revokes_tokens_and_other_sessions(): void
    {
        $this->assertSelfServicePasswordChange('author', 'author.settings.update');
    }

    public function test_reviewer_password_change_revokes_tokens_and_other_sessions(): void
    {
        $this->assertSelfServicePasswordChange('reviewer', 'reviewer.settings.password');
    }

    public function test_admin_account_password_change_revokes_tokens_and_other_sessions(): void
    {
        $this->assertSelfServicePasswordChange('super-admin', 'admin.account.update');
    }

    public function test_super_admin_managed_password_reset_revokes_managed_sessions_and_tokens(): void
    {
        $admin = $this->member('super-admin');
        $managed = $this->member('author');
        $token = $managed->createToken('prior-device', ['profile:read'])->plainTextToken;
        $this->sessionRecord($managed, 'managed-existing-session');
        $this->actingAs($admin)->put(route('admin.users.update', $managed), [
            'name' => $managed->name, 'email' => $managed->email,
            'password' => 'Fresh$Security123', 'password_confirmation' => 'Fresh$Security123',
            'roles' => $managed->roles()->pluck('id')->all(), 'is_active' => true, 'email_verified' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertRecovered($managed);
        $this->assertDatabaseMissing('sessions', ['user_id' => $managed->id]);
        $this->assertAuthenticatedAs($admin);
        $this->assertRevokedToken($token);
    }

    public function test_invalid_current_password_does_not_revoke_credentials(): void
    {
        $user = $this->member('author');
        $user->createToken('valid-device', ['profile:read']);
        $this->sessionRecord($user, 'untouched-session');
        $this->actingAs($user)->put(route('author.settings.update'), [
            'current_password' => 'wrong-password', 'password' => 'Fresh$Security123', 'password_confirmation' => 'Fresh$Security123',
        ])->assertSessionHasErrors('current_password');
        $this->assertSame(1, $user->tokens()->count());
        $this->assertDatabaseHas('sessions', ['id' => 'untouched-session']);
        $this->assertTrue(Hash::check('Initial$Security123', $user->fresh()->password));
    }

    public function test_stale_password_hash_session_is_denied_even_without_a_database_session_record(): void
    {
        config(['session.driver' => 'array']);
        $user = $this->member('author');
        $staleHash = $user->getAuthPassword();
        $user->update(['password' => 'Fresh$Security123']);
        $this->actingAs($user)->withSession(['password_hash_web' => $staleHash])
            ->getJson(route('author.dashboard'))->assertUnauthorized();
        $this->assertGuest();
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_current_self_service_session_stays_authenticated_after_the_password_hash_changes(): void
    {
        config(['session.driver' => 'array']);
        foreach (['author' => 'author.settings.update', 'reviewer' => 'reviewer.settings.password', 'super-admin' => 'admin.account.update'] as $role => $route) {
            $user = $this->member($role);
            $this->actingAs($user)->withSession(['password_hash_web' => $user->getAuthPassword()]);
            $this->put(route($route), [
                'email' => $user->email, 'current_password' => 'Initial$Security123',
                'password' => 'Fresh$Security123', 'password_confirmation' => 'Fresh$Security123',
            ])->assertSessionHasNoErrors()->assertRedirect();
            $fresh = $user->fresh();
            $sessionHash = $this->app['session']->driver()->get('password_hash_web');
            $this->assertNotSame($user->getRawOriginal('password'), $sessionHash);
            $this->assertSame($this->app['auth']->guard('web')->hashPasswordForCookie($fresh->getAuthPassword()), $sessionHash);
            $this->app['auth']->forgetGuards();
            $guard = $this->app['auth']->guard('web');
            $this->withSession([$guard->getName() => $user->id, 'password_hash_web' => $sessionHash]);
            $this->get(route($role === 'super-admin' ? 'admin.account.edit' : ($role === 'reviewer' ? 'reviewer.settings.edit' : 'author.settings.edit')))->assertOk();
            $this->assertAuthenticatedAs($fresh);
        }
    }

    private function assertSelfServicePasswordChange(string $role, string $route): void
    {
        $user = $this->member($role);
        $token = $user->createToken('prior-device', ['profile:read'])->plainTextToken;
        $this->sessionRecord($user, 'prior-browser-session');
        $this->actingAs($user)->put(route($route), [
            'email' => $user->email, 'current_password' => 'Initial$Security123',
            'password' => 'Fresh$Security123', 'password_confirmation' => 'Fresh$Security123',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertRecovered($user);
        $this->assertDatabaseMissing('sessions', ['id' => 'prior-browser-session']);
        $this->assertAuthenticatedAs($user);
        $this->assertRevokedToken($token);
    }

    private function assertRecovered(User $user): void
    {
        $this->assertTrue(Hash::check('Fresh$Security123', $user->fresh()->password));
        $this->assertNull($user->fresh()->remember_token);
        $this->assertSame(0, $user->tokens()->count());
    }

    private function assertRevokedToken(string $token): void
    {
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/user', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }

    private function sessionRecord(User $user, string $id): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $user->id, 'payload' => base64_encode('{}'), 'last_activity' => time()]);
    }

    private function member(string $role): User
    {
        $user = User::factory()->create(['password' => 'Initial$Security123', 'remember_token' => 'old-remember-token']);
        $user->roles()->attach(Role::where('slug', $role)->firstOrFail());

        return $user;
    }
}
