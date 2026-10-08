<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class RegistrationVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->seed(RolePermissionSeeder::class);
        config()->set('services.google.enabled', false);
        config()->set('security.local_admin_bypass.enabled', false);
    }

    public function test_each_role_receives_a_signup_link_and_verification_does_not_grant_access(): void
    {
        foreach (['author', 'editor', 'reviewer', 'contributor', 'admin'] as $role) {
            $this->travel(61)->seconds();
            $this->post(route($role.'.register.store'), [
                'name' => 'Applicant', 'email' => $role.'@example.test',
                'password' => 'Strong!Portal123', 'password_confirmation' => 'Strong!Portal123', 'terms' => '1',
            ])->assertRedirect(route('registration.submitted'))->assertSessionHasNoErrors();
            $user = User::query()->where('email', $role.'@example.test')->firstOrFail();

            Notification::assertSentTo($user, QueuedVerifyEmail::class, function ($notification) use ($user): bool {
                $this->assertSame('mail', $notification->queue);
                $this->assertTrue($notification->afterCommit);
                $this->get($notification->toMail($user)->actionUrl)
                    ->assertOk()->assertSeeText('Email address verified');

                return true;
            });
            $user->refresh();
            $this->assertTrue($user->hasVerifiedEmail());
            $this->assertSame('pending', $user->status);
            $this->assertFalse($user->isActive());
            $this->assertFalse($user->roles()->exists());
            $this->assertGuest();
            if ($user->author) {
                $this->assertFalse($user->author->is_active);
            }
            $this->post(route($role.'.login.store'), ['email' => $user->email, 'password' => 'Strong!Portal123'])
                ->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_verified_applicant_can_sign_in_after_super_admin_approval(): void
    {
        $user = $this->pending();
        $this->get($this->link($user))->assertOk();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());
        $this->actingAs($admin)->post(route('admin.users.approve', $user))->assertSessionHasNoErrors();
        Notification::assertNotSentTo($user, QueuedVerifyEmail::class);
        $this->post(route('logout'));
        $this->post(route('author.login.store'), ['email' => $user->email, 'password' => 'Strong!Portal123'])
            ->assertRedirect(route('author.dashboard'));
        $this->get(route('author.dashboard'))->assertOk();
    }

    public function test_signup_link_remains_usable_if_approval_happens_before_it_is_opened(): void
    {
        $user = $this->pending();
        $url = $this->link($user);
        $user->update(['status' => 'active', 'is_active' => true]);
        $this->get($url)->assertOk();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
    }

    public function test_links_reject_expiry_tampering_wrong_addresses_changed_addresses_and_rejected_accounts(): void
    {
        $user = $this->pending();
        foreach ([
            $this->link($user, -1),
            $this->link($user).'&tampered=1',
            URL::temporarySignedRoute('registration.verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('wrong@example.test')]),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
        $oldUrl = $this->link($user);
        $user->update(['email' => 'changed@example.test']);
        $this->get($oldUrl)->assertForbidden();
        $user->update(['status' => 'rejected']);
        $this->get($this->link($user))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_verification_is_idempotent_and_does_not_switch_an_existing_session(): void
    {
        Event::fake([Verified::class]);
        $user = $this->pending();
        $other = User::factory()->create();
        $url = $this->link($user);
        $this->actingAs($other)->get($url)->assertOk();
        $this->get($url)->assertOk();
        $this->assertAuthenticatedAs($other);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatchedTimes(Verified::class, 1);
    }

    public function test_public_resend_is_generic_and_only_sends_for_pending_unverified_applicants(): void
    {
        $user = $this->pending();
        $verified = $this->pending(['email' => 'verified@example.test', 'email_verified_at' => now()]);
        $rejected = $this->pending(['email' => 'rejected@example.test', 'status' => 'rejected']);
        $active = User::factory()->unverified()->create(['email' => 'active@example.test']);

        foreach ([$user->email, $verified->email, $rejected->email, $active->email, 'missing@example.test'] as $email) {
            $this->withoutMiddleware(ThrottleRequests::class)
                ->from(route('registration.verification.notice'))
                ->post(route('registration.verification.send'), ['email' => strtoupper($email)])
                ->assertRedirect(route('registration.verification.notice'))
                ->assertSessionHas('success', 'If an unverified application matches this address, a fresh verification link has been queued. Approved accounts can request a link after signing in.');
        }
        Notification::assertSentToTimes($user, QueuedVerifyEmail::class, 1);
        Notification::assertCount(1);
    }

    public function test_public_resends_are_throttled(): void
    {
        $user = $this->pending();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('registration.verification.send'), ['email' => $user->email])->assertRedirect();
        }
        $this->post(route('registration.verification.send'), ['email' => $user->email])->assertStatus(429);
        Notification::assertSentToTimes($user, QueuedVerifyEmail::class, 3);
    }

    public function test_missing_production_mail_does_not_claim_a_resend_was_queued(): void
    {
        $this->app->instance('env', 'production');
        config()->set('mail.default', 'log');
        $this->withSession(['_token' => 'registration-verification-test']);
        $user = $this->pending();
        $this->get(route('registration.submitted'))->assertOk()->assertSeeText('Email verification delivery is not available yet.');
        $this->get(route('registration.verification.notice'))->assertOk()->assertDontSeeText('Resend verification email');
        $this->post(route('registration.verification.send'), ['email' => $user->email, '_token' => 'registration-verification-test'])
            ->assertSessionHasErrors('email')->assertSessionMissing('success');
        Notification::assertNothingSent();
    }

    public function test_verified_google_application_confirmation_does_not_request_another_verification(): void
    {
        $this->withSession(['application' => ['email_verified' => true]])
            ->get(route('registration.submitted'))->assertOk()
            ->assertSeeText('Your email address is already verified.')
            ->assertDontSeeText('Check your inbox for the verification link');
    }

    private function pending(array $attributes = []): User
    {
        return User::factory()->unverified()->create(array_merge([
            'email' => 'pending@example.test', 'password' => 'Strong!Portal123',
            'status' => 'pending', 'is_active' => false, 'requested_role' => 'author',
        ], $attributes));
    }

    private function link(User $user, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('registration.verification.verify', now()->addMinutes($minutes), [
            'id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification()),
        ]);
    }
}
