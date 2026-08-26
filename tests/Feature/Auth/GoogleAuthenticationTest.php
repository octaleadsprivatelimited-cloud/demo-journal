<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.google.enabled', true);
        config()->set('services.google.client_id', 'google-client');
        config()->set('services.google.client_secret', 'google-secret');
        config()->set('services.google.redirect', 'http://localhost/auth/google/callback');
    }

    public function test_google_redirect_keeps_the_selected_workspace_in_session(): void
    {
        $response = $this->get(route('google.redirect', ['portal' => 'author']));

        $response->assertRedirectContains('accounts.google.com/o/oauth2/v2/auth');
        $this->assertSame('author', session('google_oauth.portal'));
    }

    public function test_google_author_signup_creates_a_pending_application(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token']),
            'openidconnect.googleapis.com/*' => Http::response([
                'sub' => 'google-author-id',
                'email' => 'author@example.test',
                'email_verified' => true,
                'name' => 'Google Author',
            ]),
        ]);

        $this->withSession(['google_oauth' => ['portal' => 'author', 'state' => hash('sha256', 'state')]])
            ->get(route('google.callback', ['code' => 'code', 'state' => 'state']))
            ->assertRedirect(route('registration.submitted'));

        $this->assertDatabaseHas('users', [
            'email' => 'author@example.test',
            'google_id' => 'google-author-id',
            'status' => 'pending',
            'is_active' => false,
            'requested_role' => 'author',
        ]);
        $this->assertDatabaseHas('authors', ['email' => 'author@example.test', 'is_active' => false]);
    }

    public function test_approved_reviewer_can_sign_in_with_google_to_only_the_reviewer_workspace(): void
    {
        $reviewer = User::factory()->create(['email' => 'reviewer@example.test']);
        $role = Role::query()->create(['name' => 'Reviewer', 'slug' => 'reviewer']);
        $reviewer->roles()->attach($role, ['assigned_at' => now()]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token']),
            'openidconnect.googleapis.com/*' => Http::response([
                'sub' => 'google-reviewer-id',
                'email' => 'reviewer@example.test',
                'email_verified' => true,
                'name' => 'Reviewer',
            ]),
        ]);

        $this->withSession(['google_oauth' => ['portal' => 'reviewer', 'state' => hash('sha256', 'state')]])
            ->get(route('google.callback', ['code' => 'code', 'state' => 'state']))
            ->assertRedirect(route('reviewer.dashboard'));

        $this->assertAuthenticatedAs($reviewer->fresh());
        $this->assertSame('google-reviewer-id', $reviewer->fresh()->google_id);
    }
}
