<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class RolePortalAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Strong!Portal123';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('publication.features.author_registration', true);
        config()->set('security.local_admin_bypass.enabled', false);
    }

    public function test_each_role_has_distinct_login_and_registration_pages_and_actions(): void
    {
        $loginRoutes = [];
        $registrationRoutes = [];

        foreach (['author', 'editor', 'admin'] as $role) {
            $loginRoutes[] = route("{$role}.login");
            $registrationRoutes[] = route("{$role}.register");

            $this->get(route("{$role}.login"))
                ->assertOk()
                ->assertSeeText(str($role)->headline()->toString())
                ->assertSee('action="'.route("{$role}.login.store").'"', false);

            $this->get(route("{$role}.register"))
                ->assertOk()
                ->assertSeeText(str($role)->headline()->toString())
                ->assertSee('action="'.route("{$role}.register.store").'"', false);
        }

        $this->assertCount(3, array_unique($loginRoutes));
        $this->assertCount(3, array_unique($registrationRoutes));
    }

    public function test_each_registration_creates_an_inactive_pending_request_without_a_role_or_session(): void
    {
        Notification::fake();

        foreach (['author', 'editor', 'admin'] as $role) {
            $email = "{$role}.applicant@example.test";

            $this->post(route("{$role}.register.store"), $this->registrationPayload($role, $email))
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame('pending', $user->status);
            $this->assertFalse($user->is_active);
            $this->assertSame($role, $user->requested_role);
            $this->assertNull($user->approved_by_id);
            $this->assertNull($user->approved_at);
            $this->assertNull($user->rejected_at);
            $this->assertFalse($user->roles()->exists());
            $this->assertGuest();
        }
    }

    public function test_registration_role_fields_cannot_escalate_the_server_derived_request(): void
    {
        Notification::fake();

        foreach (['author', 'editor', 'admin'] as $role) {
            $email = "tampered.{$role}@example.test";
            $payload = $this->registrationPayload($role, $email) + [
                'role' => 'super-admin',
                'requested_role' => 'super-admin',
            ];

            $this->post(route("{$role}.register.store"), $payload)
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame($role, $user->requested_role);
            $this->assertFalse($user->roles()->exists());
            $this->assertFalse($user->isActive());
            $this->assertGuest();
        }
    }

    public function test_verified_users_are_redirected_by_the_matching_role_portal(): void
    {
        foreach (['author', 'editor', 'admin'] as $role) {
            $user = $this->approvedUser($role);

            $this->post(route("{$role}.login.store"), [
                'email' => $user->email,
                'password' => self::PASSWORD,
            ])->assertRedirect(route("{$role}.dashboard"));

            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'))->assertRedirect(route('home'));
            $this->assertGuest();
        }
    }

    public function test_valid_credentials_are_rejected_when_submitted_to_a_different_role_portal(): void
    {
        $users = collect(['author', 'editor', 'admin'])
            ->mapWithKeys(fn (string $role): array => [$role => $this->approvedUser($role)]);

        $mismatches = [
            ['author', 'editor'],
            ['author', 'admin'],
            ['editor', 'author'],
            ['editor', 'admin'],
            ['admin', 'author'],
            ['admin', 'editor'],
        ];

        foreach ($mismatches as [$userRole, $portal]) {
            $user = $users->get($userRole);

            $this->from(route("{$portal}.login"))
                ->post(route("{$portal}.login.store"), [
                    'email' => $user->email,
                    'password' => self::PASSWORD,
                ])
                ->assertRedirect(route("{$portal}.login"))
                ->assertSessionHasErrors('email');

            $this->assertGuest();
        }
    }

    public function test_pending_application_cannot_sign_in_before_super_admin_approval(): void
    {
        $applicant = $this->pendingApplicant('editor');

        $this->from(route('editor.login'))
            ->post(route('editor.login.store'), [
                'email' => $applicant->email,
                'password' => self::PASSWORD,
            ])
            ->assertRedirect(route('editor.login'))
            ->assertSessionHasErrors([
                'email' => 'Your application is awaiting Super Admin approval.',
            ]);

        $this->assertGuest();
    }

    public function test_super_administrator_signs_in_through_the_admin_portal(): void
    {
        $superAdmin = $this->approvedUser('super-admin');

        $this->post(route('admin.login.store'), [
            'email' => $superAdmin->email,
            'password' => self::PASSWORD,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($superAdmin);
    }

    public function test_only_a_super_administrator_can_decide_a_pending_registration(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $applicant = $this->pendingApplicant('editor');

        foreach (['approve', 'reject'] as $decision) {
            $this->post(route("admin.users.{$decision}", $applicant))
                ->assertRedirect(route('admin.login'));
        }

        foreach (['author', 'editor', 'admin'] as $role) {
            foreach (['approve', 'reject'] as $decision) {
                $this->actingAs($this->roleUser($role))
                    ->post(route("admin.users.{$decision}", $applicant))
                    ->assertForbidden();
            }

            $applicant->refresh();
            $this->assertSame('pending', $applicant->status);
            $this->assertFalse($applicant->is_active);
            $this->assertFalse($applicant->roles()->exists());
        }
    }

    public function test_super_administrator_approval_activates_the_account_and_grants_only_the_requested_role(): void
    {
        $this->seed(RolePermissionSeeder::class);
        Notification::fake();

        $superAdmin = $this->roleUser('super-admin');
        $applicant = $this->pendingApplicant('editor');

        $this->actingAs($superAdmin)
            ->post(route('admin.users.approve', $applicant))
            ->assertRedirect();

        $applicant->refresh();

        $this->assertSame('active', $applicant->status);
        $this->assertTrue($applicant->is_active);
        $this->assertSame($superAdmin->getKey(), $applicant->approved_by_id);
        $this->assertNotNull($applicant->approved_at);
        $this->assertNull($applicant->rejected_at);
        $this->assertSame(['editor'], $applicant->roles()->pluck('slug')->all());
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $superAdmin->getKey(),
            'auditable_type' => $applicant->getMorphClass(),
            'auditable_id' => $applicant->getKey(),
            'event' => 'account_application_approved',
        ]);
        Notification::assertSentTo($applicant, VerifyEmail::class);
    }

    public function test_super_administrator_can_reject_a_pending_application_with_an_audit_record(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $superAdmin = $this->roleUser('super-admin');
        $applicant = $this->pendingApplicant('admin');

        $this->actingAs($superAdmin)
            ->post(route('admin.users.reject', $applicant))
            ->assertRedirect();

        $applicant->refresh();

        $this->assertSame('rejected', $applicant->status);
        $this->assertFalse($applicant->is_active);
        $this->assertSame($superAdmin->getKey(), $applicant->approved_by_id);
        $this->assertNull($applicant->approved_at);
        $this->assertNotNull($applicant->rejected_at);
        $this->assertFalse($applicant->roles()->exists());
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $superAdmin->getKey(),
            'auditable_type' => $applicant->getMorphClass(),
            'auditable_id' => $applicant->getKey(),
            'event' => 'account_application_rejected',
        ]);
    }

    public function test_admin_and_editor_cannot_manage_users_roles_or_website_settings(): void
    {
        $this->seed(RolePermissionSeeder::class);

        foreach (['admin', 'editor'] as $role) {
            $actor = $this->roleUser($role);

            $this->actingAs($actor)->get(route('admin.users.index'))->assertForbidden();
            $this->actingAs($actor)->get(route('admin.roles.index'))->assertForbidden();
            $this->actingAs($actor)->get(route('admin.settings.index'))->assertForbidden();
        }
    }

    /** @return array<string, string> */
    private function registrationPayload(string $role, string $email): array
    {
        return [
            'name' => str($role)->headline()->append(' Applicant')->toString(),
            'email' => $email,
            'organization' => 'Octaleads Journal',
            'designation' => str($role)->headline()->toString(),
            'biography' => 'A prospective member of the journal team.',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'terms' => '1',
        ];
    }

    private function approvedUser(string $role): User
    {
        $user = User::factory()->create([
            'email' => "approved.{$role}.".fake()->unique()->randomNumber(6).'@example.test',
            'password' => self::PASSWORD,
            'email_verified_at' => now(),
            'status' => 'active',
            'is_active' => true,
            'requested_role' => null,
            'approved_at' => now(),
        ]);

        $user->roles()->attach($this->role($role));

        return $user;
    }

    private function pendingApplicant(string $requestedRole): User
    {
        return User::factory()->create([
            'password' => self::PASSWORD,
            'email_verified_at' => null,
            'status' => 'pending',
            'is_active' => false,
            'requested_role' => $requestedRole,
            'approved_by_id' => null,
            'approved_at' => null,
            'rejected_at' => null,
        ]);
    }

    private function roleUser(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach($this->role($role));

        return $user;
    }

    private function role(string $slug): Role
    {
        return Role::query()->firstOrCreate(
            ['slug' => $slug],
            ['name' => str($slug)->headline()->toString(), 'is_system' => true],
        );
    }
}
