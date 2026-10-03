<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class VerificationDeliveryAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config()->set('security.local_admin_bypass.enabled', false);
        $this->withSession(['_token' => 'verification-delivery-test']);
    }

    #[DataProvider('unavailableMailers')]
    public function test_production_without_email_delivery_reports_unavailability_and_does_not_queue_a_resend(?string $mailer): void
    {
        $this->app->instance('env', 'production');
        config()->set('mail.default', $mailer);
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Email delivery unavailable')
            ->assertSee('Please contact the journal administrator.')
            ->assertDontSee('We sent a signed verification link')
            ->assertDontSee('Resend verification email');

        $this->from(route('verification.notice'))->post(route('verification.send'), ['_token' => 'verification-delivery-test'])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasErrors(['email' => 'Email verification delivery is not available yet. Please contact the journal administrator.'])
            ->assertSessionMissing('success');

        Notification::assertNothingSent();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public static function unavailableMailers(): array
    {
        return [
            'log' => ['log'],
            'array' => ['array'],
            'missing' => [null],
            'empty' => [''],
        ];
    }

    public function test_configured_production_delivery_queues_verification_without_claiming_it_was_sent(): void
    {
        $this->app->instance('env', 'production');
        config()->set('mail.default', 'smtp');
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Resend verification email')
            ->assertDontSee('We sent a signed verification link');

        $this->from(route('verification.notice'))->post(route('verification.send'), ['_token' => 'verification-delivery-test'])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'A fresh verification link has been queued for delivery to your email address.');

        Notification::assertSentTo($user, QueuedVerifyEmail::class, fn ($notification): bool => $notification->queue === 'mail' && $notification->afterCommit === true);
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_testing_delivery_still_queues_verification_with_the_array_mailer(): void
    {
        config()->set('mail.default', 'array');
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->from(route('verification.notice'))->post(route('verification.send'), ['_token' => 'verification-delivery-test'])
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, QueuedVerifyEmail::class);
    }

    public function test_verified_users_keep_their_redirect_when_delivery_is_unavailable(): void
    {
        $this->app->instance('env', 'production');
        config()->set('mail.default', 'log');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('verification.send'), ['_token' => 'verification-delivery-test'])
            ->assertRedirect(route('home'))
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }
}
