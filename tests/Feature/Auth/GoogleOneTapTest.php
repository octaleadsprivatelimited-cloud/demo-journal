<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleOneTapTest extends TestCase
{
    use RefreshDatabase;

    private $key;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.enabled' => true, 'services.google.only' => true, 'services.google.client_id' => 'test-client', 'services.google.client_secret' => 'test-secret']);
        Cache::forget('google.signing_keys');
        $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($this->key);
        $encode = fn ($value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        Http::fake(['www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [[
            'kty' => 'RSA', 'kid' => 'test-key', 'alg' => 'RS256', 'use' => 'sig',
            'n' => $encode($details['rsa']['n']), 'e' => $encode($details['rsa']['e']),
        ]]], 200, ['Cache-Control' => 'public, max-age=3600'])]);
    }

    private function token(array $changes = []): string
    {
        return JWT::encode(array_merge([
            'iss' => 'https://accounts.google.com', 'aud' => 'test-client', 'sub' => 'google-person',
            'email' => 'person@gmail.com', 'email_verified' => true, 'name' => 'Google Person',
            'iat' => time() - 10, 'exp' => time() + 300, 'nonce' => 'test-nonce',
        ], $changes), $this->key, 'RS256', 'test-key');
    }

    private function signIn(string $portal, array $claims = [])
    {
        return $this->withSession(['google_one_tap' => [$portal => ['nonce' => 'test-nonce', 'expires' => time() + 600]]])
            ->post(route('google.one-tap', $portal), ['credential' => $this->token($claims)]);
    }

    public function test_every_role_has_google_only_login_and_signup_without_password_fields(): void
    {
        foreach (['author', 'editor', 'reviewer', 'contributor', 'admin'] as $portal) {
            foreach (['login', 'register'] as $page) {
                $this->get('/'.$portal.'/'.$page)->assertOk()->assertSee('accounts.google.com/gsi/client', false)
                    ->assertDontSee('name="password"', false)->assertSee('google-signin-button', false);
            }
            $this->get(route('google.redirect', $portal))->assertRedirectContains('accounts.google.com');
            $this->post('/'.$portal.'/login', ['email' => 'person@gmail.com', 'password' => 'password'])->assertSessionHasErrors('google');
            $this->post('/'.$portal.'/register', [])->assertSessionHasErrors('google');
        }
    }

    public function test_new_google_admin_is_pending_and_has_no_privileged_role(): void
    {
        $this->signIn('admin')->assertRedirect(route('registration.submitted'));
        $user = User::where('email', 'person@gmail.com')->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertSame('admin', $user->requested_role);
        $this->assertSame(0, $user->roles()->count());
        $this->assertGuest();
    }

    public function test_approved_contributor_signs_in_without_a_password_and_nonce_cannot_be_replayed(): void
    {
        $user = User::factory()->create(['email' => 'person@gmail.com']);
        $role = Role::create(['name' => 'Contributor', 'slug' => 'contributor']);
        $user->roles()->attach($role);
        $this->signIn('contributor')->assertRedirect(route('author.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('google_one_tap.contributor'));
    }

    public function test_invalid_audience_expiry_nonce_and_unverified_email_do_not_create_accounts(): void
    {
        foreach ([['aud' => 'wrong-client'], ['exp' => time() - 5], ['nonce' => 'wrong-nonce'], ['email_verified' => false], ['iss' => 'https://attacker.test']] as $claims) {
            $this->signIn('author', $claims)->assertRedirect(route('author.login'))->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->assertDatabaseCount('users', 0);
        }
    }

    public function test_wrong_workspace_does_not_grant_access(): void
    {
        $user = User::factory()->create(['email' => 'person@gmail.com']);
        $role = Role::create(['name' => 'Author', 'slug' => 'author']);
        $user->roles()->attach($role);
        $this->signIn('admin')->assertRedirect(route('admin.login'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertFalse($user->fresh()->hasRole('admin'));
    }

    public function test_third_party_email_cannot_take_over_an_existing_unlinked_account(): void
    {
        $user = User::factory()->create(['email' => 'person@third-party.test']);
        $this->signIn('admin', ['email' => $user->email])->assertSessionHasErrors('email');
        $this->assertNull($user->fresh()->google_id);
        $this->assertGuest();
    }

    public function test_password_reset_is_replaced_by_google_access(): void
    {
        $this->get('/forgot-password')->assertRedirect(route('login'));
        $this->post('/forgot-password', ['email' => 'person@gmail.com'])->assertSessionHasErrors('google');
    }

    public function test_forged_signature_and_missing_session_are_rejected(): void
    {
        $this->key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->signIn('author')->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 0);
        $this->post(route('google.one-tap', 'author'), ['credential' => $this->token()])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_access_remains_available_until_google_is_configured(): void
    {
        config(['services.google.enabled' => false]);
        $this->get('/author/login')->assertOk()->assertSee('name="password"', false);
    }
}
