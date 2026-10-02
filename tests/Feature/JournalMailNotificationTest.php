<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\ArticleSubmitted;
use App\Listeners\NotifyEditorsOfArticleSubmission;
use App\Models\Article;
use App\Models\Role;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\AccountApplicationReceivedNotification;
use App\Notifications\AccountApplicationStatusNotification;
use App\Notifications\ArticleSubmittedNotification;
use App\Notifications\ContactSubmissionReceivedNotification;
use App\Notifications\ContactSubmissionReceiptNotification;
use App\Notifications\QueuedVerifyEmail;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class JournalMailNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config()->set('publication.contact_email', 'info@larixjournals.com');
        config()->set('publication.account_notification_email', 'info@larixjournals.com');
        config()->set('publication.features.author_registration', true);
        config()->set('security.local_admin_bypass.enabled', false);
    }

    public function test_contact_form_sends_one_office_alert_and_one_receipt_without_an_admin_account(): void
    {
        $this->post(route('contact.store'), $this->contactPayload())->assertRedirect()->assertSessionHasNoErrors();

        Notification::assertCount(2);
        Notification::assertSentOnDemand(ContactSubmissionReceivedNotification::class, function ($notification, $channels, $notifiable): bool {
            $this->assertSame(['mail'], $notification->via($notifiable));
            $mail = $notification->toMail($notifiable);
            $this->assertSame('visitor@example.test', $mail->replyTo[0][0]);
            $this->assertStringContainsString('Please help with my manuscript submission.', (string) $mail->render());

            return $channels === ['mail'] && $notifiable->routes['mail'] === 'info@larixjournals.com';
        });
        Notification::assertSentOnDemand(ContactSubmissionReceiptNotification::class, fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === 'visitor@example.test');
    }

    public function test_office_mailbox_is_not_notified_twice_when_it_is_an_active_staff_account(): void
    {
        $admin = $this->staff('admin', 'INFO@larixjournals.com');
        $this->post(route('contact.store'), $this->contactPayload())->assertRedirect();

        Notification::assertSentToTimes($admin, ContactSubmissionReceivedNotification::class, 1);
        Notification::assertSentOnDemandTimes(ContactSubmissionReceivedNotification::class, 0);
        Notification::assertSentTo($admin, ContactSubmissionReceivedNotification::class, fn ($notification, $channels): bool => $channels === ['database', 'mail']);
    }

    public function test_invalid_or_honeypot_contact_submissions_send_no_mail(): void
    {
        $this->post(route('contact.store'), array_merge($this->contactPayload(), ['company_website' => 'https://spam.test']))->assertSessionHasErrors('company_website');
        $this->assertDatabaseCount('contact_submissions', 0);
        Notification::assertNothingSent();
    }

    public function test_role_applications_send_receipts_and_office_alerts_while_remaining_pending(): void
    {
        foreach (['author', 'editor', 'reviewer', 'contributor', 'admin'] as $role) {
            $this->post(route($role.'.register.store'), [
                'name' => 'Journal Applicant', 'email' => $role.'@example.test',
                'password' => 'Strong!Portal123', 'password_confirmation' => 'Strong!Portal123', 'terms' => '1',
            ])->assertRedirect(route('registration.submitted'))->assertSessionHasNoErrors();
            $applicant = User::query()->where('email', $role.'@example.test')->firstOrFail();
            $this->assertFalse($applicant->is_active);
            $this->assertFalse($applicant->roles()->exists());
            Notification::assertSentTo($applicant, AccountApplicationStatusNotification::class, fn ($notification): bool => $notification->status === 'pending');
            Notification::assertSentOnDemand(AccountApplicationReceivedNotification::class, fn ($notification, $channels, $notifiable): bool => $notification->applicant->is($applicant) && $channels === ['mail'] && $notifiable->routes['mail'] === 'info@larixjournals.com');
        }
    }

    public function test_already_verified_applicants_receive_an_approval_email_with_the_correct_portal_link(): void
    {
        $super = $this->staff('super-admin');
        foreach (['editor', 'reviewer', 'contributor'] as $role) {
            $applicant = User::factory()->create(['status' => 'pending', 'is_active' => false, 'requested_role' => $role]);
            $this->actingAs($super)->post(route('admin.users.approve', $applicant))->assertRedirect()->assertSessionHasNoErrors();
            Notification::assertSentTo($applicant, AccountApplicationStatusNotification::class, function ($notification) use ($applicant, $role): bool {
                $this->assertSame(route($role.'.login'), $notification->toMail($applicant)->actionUrl);
                $this->assertStringContainsString('approved', (string) $notification->toMail($applicant)->render());

                return $notification->status === 'active';
            });
            Notification::assertNotSentTo($applicant, QueuedVerifyEmail::class);
            $this->assertSame([$role], $applicant->fresh()->roles()->pluck('slug')->all());
        }
    }

    public function test_unverified_applicants_receive_queued_verification_and_approval_messages(): void
    {
        $super = $this->staff('super-admin');
        $applicant = User::factory()->unverified()->create(['status' => 'pending', 'is_active' => false, 'requested_role' => 'author']);
        $this->actingAs($super)->post(route('admin.users.approve', $applicant))->assertRedirect();

        Notification::assertSentTo($applicant, QueuedVerifyEmail::class, fn ($notification): bool => $notification->queue === 'mail' && $notification->afterCommit === true);
        Notification::assertSentTo($applicant, AccountApplicationStatusNotification::class, fn ($notification): bool => $notification->status === 'active');
        Notification::assertSentOnDemand(AccountApplicationStatusNotification::class, fn ($notification, $channels, $notifiable): bool => $notification->status === 'active' && $notifiable->routes['mail'] === 'info@larixjournals.com');
    }

    public function test_rejection_notifies_the_applicant_and_office_without_activating_the_account(): void
    {
        $super = $this->staff('super-admin');
        $applicant = User::factory()->create(['status' => 'pending', 'is_active' => false, 'requested_role' => 'admin']);
        $this->actingAs($super)->post(route('admin.users.reject', $applicant))->assertRedirect();

        Notification::assertSentTo($applicant, AccountApplicationStatusNotification::class, fn ($notification): bool => $notification->status === 'rejected');
        Notification::assertSentOnDemand(AccountApplicationStatusNotification::class, fn ($notification): bool => $notification->status === 'rejected');
        $this->assertFalse($applicant->fresh()->is_active);
        $this->assertFalse($applicant->roles()->exists());
    }

    public function test_manuscript_office_alert_can_be_delivered_without_a_staff_account(): void
    {
        $article = Article::factory()->create(['assigned_editor_id' => null]);
        $submission = Submission::factory()->create(['article_id' => $article->id]);
        app(NotifyEditorsOfArticleSubmission::class)->handle(new ArticleSubmitted($article, $submission));

        Notification::assertSentOnDemand(ArticleSubmittedNotification::class, function ($notification, $channels, $notifiable): bool {
            $this->assertStringContainsString('Editorial office', (string) $notification->toMail($notifiable)->render());

            return $notification->via($notifiable) === ['mail'] && $notifiable->routes['mail'] === 'info@larixjournals.com';
        });
    }

    public function test_configuration_check_reports_disabled_delivery_and_missing_credentials_without_sending_mail(): void
    {
        $this->artisan('mail:check')->expectsOutputToContain('SMTP delivery is not enabled')->assertFailed();
        config()->set('mail.default', 'smtp');
        config()->set('app.env', 'production');
        config()->set('mail.mailers.smtp.username', 'info@larixjournals.com');
        config()->set('mail.mailers.smtp.password', '');
        $this->artisan('mail:check --authenticate')->expectsOutputToContain('SMTP credentials are missing')->assertFailed();
        Notification::assertNothingSent();
    }

    public function test_smtp_authentication_errors_do_not_expose_credentials(): void
    {
        config()->set('mail.default', 'smtp');
        $transport = \Mockery::mock(\Symfony\Component\Mailer\Transport\Smtp\SmtpTransport::class);
        $transport->shouldReceive('start')->once()->andThrow(new \RuntimeException('smtp-password-sensitive'));
        $transport->shouldReceive('stop')->once();
        $mailer = \Mockery::mock(\Illuminate\Mail\Mailer::class);
        $mailer->shouldReceive('getSymfonyTransport')->once()->andReturn($transport);
        \Illuminate\Support\Facades\Mail::shouldReceive('mailer')->with('smtp')->once()->andReturn($mailer);

        $this->artisan('mail:check --authenticate')
            ->expectsOutputToContain('SMTP check failed (RuntimeException)')
            ->doesntExpectOutputToContain('smtp-password-sensitive')
            ->assertFailed();
    }

    private function staff(string $role, ?string $email = null): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create($email ? ['email' => $email] : []);
        $user->roles()->attach(Role::query()->where('slug', $role)->firstOrFail(), ['assigned_at' => now()]);

        return $user;
    }

    private function contactPayload(): array
    {
        return ['name' => 'Journal Visitor', 'email' => 'visitor@example.test', 'subject' => 'Submission assistance', 'message' => 'Please help with my manuscript submission.', 'company_website' => ''];
    }
}
