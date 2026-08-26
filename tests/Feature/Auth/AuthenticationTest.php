<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_registration_creates_a_pending_inactive_application(): void
    {
        config()->set('publication.features.author_registration', true);
        $response = $this->post(route('author.register.store'), [
            'name' => 'Ada Researcher', 'email' => 'ada@example.com', 'phone' => '+1 555 123 4567',
            'organization' => 'Meridian Institute', 'designation' => 'Research Fellow', 'biography' => 'Studies public knowledge.',
            'password' => 'Strong!Author123', 'password_confirmation' => 'Strong!Author123', 'terms' => '1',
        ]);

        $response->assertRedirect(route('registration.submitted'));
        $user = User::query()->where('email', 'ada@example.com')->firstOrFail();
        $this->assertGuest();
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertSame('pending', $user->status);
        $this->assertFalse($user->is_active);
        $this->assertSame('author', $user->requested_role);
        $this->assertFalse($user->roles()->exists());
        $this->assertDatabaseHas('authors', [
            'user_id' => $user->id,
            'organization' => 'Meridian Institute',
            'is_active' => false,
            'is_verified' => false,
        ]);
    }

    public function test_author_registration_routes_are_unavailable_when_registration_is_disabled(): void
    {
        config()->set('publication.features.author_registration', false);

        $payload = [
            'name' => 'Blocked Researcher',
            'email' => 'blocked@example.com',
            'password' => 'Strong!Author123',
            'password_confirmation' => 'Strong!Author123',
            'terms' => '1',
        ];

        $this->get('/author/register')->assertNotFound();
        $this->post('/author/register', $payload)->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    }

    public function test_registration_chooser_does_not_link_to_disabled_author_registration(): void
    {
        config()->set('publication.features.author_registration', false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSeeText('Author applications are closed')
            ->assertDontSee('href="'.route('author.register').'"', false)
            ->assertSee('href="'.route('editor.register').'"', false)
            ->assertSee('href="'.route('admin.register').'"', false);

        $this->get(route('author.login'))
            ->assertOk()
            ->assertDontSee('href="'.route('author.register').'"', false);
    }

    public function test_inactive_accounts_cannot_sign_in(): void
    {
        $user = User::factory()->create(['email' => 'inactive@example.com', 'password' => 'Strong!Author123', 'is_active' => false]);
        $this->post(route('author.login.store'), ['email' => $user->email, 'password' => 'Strong!Author123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
