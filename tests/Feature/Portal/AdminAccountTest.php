<?php

namespace Tests\Feature\Portal;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AccountUpdatedNotification;
use App\Services\EmailPresentation;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminAccountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['password' => 'Initial$123', 'is_active' => true, 'status' => 'active', 'email_verified_at' => now()]);
        $user->roles()->attach(Role::where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    public function test_admin_can_change_email_and_password_but_must_know_current_password(): void
    {
        Notification::fake();
        $user = $this->admin();
        $this->actingAs($user)->get(route('admin.account.edit'))->assertOk()->assertSee('Current password');
        $data = ['email' => 'updated@example.org', 'current_password' => 'incorrect', 'password' => 'Updated$123', 'password_confirmation' => 'Updated$123'];
        $this->put(route('admin.account.update'), $data)->assertSessionHasErrors('current_password');
        $this->assertNotSame('updated@example.org', $user->fresh()->email);
        $data['current_password'] = 'Initial$123';
        $this->put(route('admin.account.update'), $data)->assertSessionHasNoErrors();
        $this->assertSame('updated@example.org', $user->fresh()->email);
        $this->assertTrue(Hash::check('Updated$123', $user->fresh()->password));
        $this->assertTrue($user->fresh()->hasRole('super-admin'));
        Notification::assertSentOnDemand(AccountUpdatedNotification::class);
    }

    public function test_duplicate_email_and_non_admin_access_are_rejected(): void
    {
        $user = $this->admin();
        $other = User::factory()->create();
        $this->actingAs($user)->put(route('admin.account.update'), ['email' => $other->email, 'current_password' => 'Initial$123'])->assertSessionHasErrors('email');
        $this->flushSession();
        $this->actingAs($other)->get(route('admin.account.edit'))->assertForbidden();
    }

    public function test_notification_and_password_reset_emails_share_logo_and_current_contact_details(): void
    {
        $user = $this->admin();
        Setting::put('site.name', 'Test Journal', 'general', true);
        Setting::put('contact.email', 'editorial@journal.example', 'general', true);
        Setting::put('contact.phone', '1234567890', 'general', true);
        $this->actingAs($user)->get(route('admin.email-templates'))->assertOk()->assertSee('Preview: Reset your password');
        $this->get(route('admin.email-templates', ['preview' => 'reset']))->assertOk()->assertSee('larix-logo-transparent.png');
        foreach ([(new AccountUpdatedNotification(true, true))->toMail($user), (new ResetPassword('test-token'))->toMail($user)] as $mail) {
            $html = (string) $mail->render();
            $this->assertStringContainsString('larix-logo-transparent.png', $html);
            $this->assertStringContainsString('Test Journal', $html);
            $this->assertStringContainsString('editorial@journal.example', $html);
            $this->assertStringContainsString('1234567890', $html);
        }
    }

    public function test_email_designs_are_distinct_and_keep_password_reset_link(): void
    {
        $user = $this->admin();
        foreach (EmailPresentation::designs() as $type => $design) {
            $html = (string) EmailPresentation::preview($type)->render();
            $this->assertStringContainsString(e($design[1]), $html);
            $this->assertStringContainsString($design[2], $html);
            $this->assertStringContainsString('SAMPLE PREVIEW', $html);
        }
        $html = (string) (new ResetPassword('secure-test-token'))->toMail($user)->render();
        $this->assertStringContainsString('Choose a new password', $html);
        $this->assertStringContainsString('secure-test-token', $html);
        $this->assertStringNotContainsString('SAMPLE PREVIEW', $html);
    }
}
