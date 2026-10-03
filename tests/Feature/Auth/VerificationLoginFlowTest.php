<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Support\PortalDestination;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class VerificationLoginFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Strong!Portal123';

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->seed(RolePermissionSeeder::class);
        config()->set('services.google.enabled', false);
        config()->set('security.local_admin_bypass.enabled', false);
    }

    public function test_approved_applicants_can_finish_verification_after_signing_in_from_the_email_link(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        foreach (['author', 'editor', 'reviewer', 'contributor', 'admin'] as $portal) {
            $user = User::factory()->unverified()->create([
                'password' => self::PASSWORD,
                'status' => 'pending',
                'is_active' => false,
                'requested_role' => $portal,
            ]);

            $this->actingAs($superAdmin)->post(route('admin.users.approve', $user))
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->post(route('logout'))->assertRedirect();

            $url = $this->verificationUrl($user);
            $this->get($url)->assertRedirect(route('login'));
            $this->post(route($portal.'.login.store'), [
                'email' => $user->email,
                'password' => self::PASSWORD,
            ])->assertRedirect($url)->assertSessionMissing('url.intended')
                ->assertSessionHas('password_hash_web', Auth::guard('web')->hashPasswordForCookie($user->getAuthPassword()));

            $this->assertAuthenticatedAs($user);
            $this->assertFalse($user->fresh()->hasVerifiedEmail());
            $dashboard = route(PortalDestination::routeNameForPortal($portal));
            $this->get($url)->assertRedirect($dashboard);
            $this->assertTrue($user->fresh()->hasVerifiedEmail());
            $this->get($dashboard)->assertOk();
            $this->post(route('logout'))->assertRedirect();
        }
    }

    public function test_login_discards_untrusted_or_invalid_verification_destinations(): void
    {
        config()->set('publication.rate_limits.login', 20);
        $user = $this->approvedAuthor();
        $other = User::factory()->unverified()->create();
        $valid = $this->verificationUrl($user);
        URL::forceRootUrl('https://external.example');
        $externalSignedUrl = $this->verificationUrl($user);
        URL::forceRootUrl(null);
        $invalidUrls = [
            $externalSignedUrl,
            $this->verificationUrl($other),
            URL::temporarySignedRoute('verification.verify', now()->addHour(), [
                'id' => $user->getKey(), 'hash' => sha1('different@example.test'),
            ]),
            URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
                'id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification()),
            ]),
            $valid.'&tampered=1',
            $valid.'#fragment',
            route('author.dashboard'),
            '/verify-email/'.$user->getKey().'/'.sha1($user->email),
            'https://[malformed',
            ['unexpected' => 'array'],
        ];

        foreach ($invalidUrls as $url) {
            $this->withSession(['url.intended' => $url])
                ->post(route('author.login.store'), ['email' => $user->email, 'password' => self::PASSWORD])
                ->assertRedirect(route('verification.notice'))
                ->assertSessionMissing('url.intended');
            $this->assertFalse($user->fresh()->hasVerifiedEmail());
            $this->post(route('logout'))->assertRedirect();
        }
    }

    public function test_a_verification_destination_does_not_bypass_the_selected_portal_role(): void
    {
        $user = $this->approvedAuthor();

        $this->from(route('editor.login'))->withSession(['url.intended' => $this->verificationUrl($user)])
            ->post(route('editor.login.store'), ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect(route('editor.login'))->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_pending_and_rejected_applicants_cannot_resume_verification_through_login(): void
    {
        foreach (['pending', 'rejected'] as $status) {
            $user = User::factory()->unverified()->create([
                'password' => self::PASSWORD, 'status' => $status,
                'is_active' => false, 'requested_role' => 'author',
            ]);

            $this->withSession(['url.intended' => $this->verificationUrl($user)])
                ->post(route('author.login.store'), ['email' => $user->email, 'password' => self::PASSWORD])
                ->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->assertFalse($user->fresh()->hasVerifiedEmail());
        }
    }

    private function approvedAuthor(): User
    {
        $user = User::factory()->unverified()->create(['password' => self::PASSWORD]);
        $user->roles()->attach(Role::query()->where('slug', 'author')->firstOrFail());

        return $user;
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }
}
