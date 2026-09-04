<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\User;
use App\Support\PortalDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

final class GoogleAuthenticationController extends Controller
{
    /** @var array<string, string> */
    private const PORTAL_ROLES = [
        'author' => 'author',
        'editor' => 'editor',
        'reviewer' => 'reviewer',
        'admin' => 'admin',
        'contributor' => 'contributor',
    ];

    public function redirect(Request $request, string $portal): RedirectResponse
    {
        $this->assertEnabled();

        $state = Str::random(64);
        $request->session()->put('google_oauth', [
            'portal' => $portal,
            'state' => hash('sha256', $state),
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->assertEnabled();

        $oauth = $request->session()->pull('google_oauth');
        $portal = is_array($oauth) ? ($oauth['portal'] ?? null) : null;
        $expectedState = is_array($oauth) ? ($oauth['state'] ?? null) : null;
        $providedState = $request->query('state');

        if (! is_string($portal) || ! array_key_exists($portal, self::PORTAL_ROLES)
            || ! is_string($expectedState) || ! is_string($providedState)
            || ! hash_equals($expectedState, hash('sha256', $providedState))) {
            abort(419, 'Your Google sign-in session expired. Please try again.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return $this->failure($portal, 'Google sign-in was cancelled or could not be completed.');
        }

        try {
            $token = Http::asForm()->timeout(10)
                ->post('https://oauth2.googleapis.com/token', [
                    'code' => (string) $request->input('code'),
                    'client_id' => config('services.google.client_id'),
                    'client_secret' => config('services.google.client_secret'),
                    'redirect_uri' => config('services.google.redirect'),
                    'grant_type' => 'authorization_code',
                ])->throw()->json();

            $profile = Http::withToken((string) ($token['access_token'] ?? ''))->timeout(10)
                ->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
        } catch (Throwable $exception) {
            report($exception);

            return $this->failure($portal, 'Google sign-in is temporarily unavailable. Please try again.');
        }

        return $this->complete($request, $portal, $profile);
    }

    private function complete(Request $request, string $portal, array $profile): RedirectResponse
    {
        $googleId = $profile['sub'] ?? null;
        $email = isset($profile['email']) ? Str::lower((string) $profile['email']) : null;

        if (! is_string($googleId) || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ($profile['email_verified'] ?? false) !== true) {
            return $this->failure($portal, 'Google did not provide a verified email address for this account.');
        }

        $user = User::query()->where('google_id', $googleId)->first()
            ?? User::query()->where('email', $email)->first();

        if (! $user) {
            if ($portal === 'author' && ! app(\App\Services\PublicationSettings::class)->featureEnabled('author_registration')) {
                return $this->failure($portal, '[registration_closed] New author registration is currently closed.');
            }
            $user = $this->createPendingApplicant($profile, $email, $googleId, self::PORTAL_ROLES[$portal]);

            return redirect()->route('registration.submitted')->with('application', [
                'email' => $user->email,
                'role' => Str::headline(self::PORTAL_ROLES[$portal]),
            ]);
        }

        if ($user->google_id && $user->google_id !== $googleId) {
            return $this->failure($portal, 'This email address is already connected to a different Google account.');
        }

        if (! $user->google_id && ! str_ends_with($email, '@gmail.com') && empty($profile['hd'])) {
            return $this->failure($portal, '[google_link_required] This existing account must be linked by the administrator before Google sign-in can be used.');
        }

        $user->forceFill([
            'google_id' => $googleId,
            'google_avatar_url' => is_string($profile['picture'] ?? null) ? $profile['picture'] : $user->google_avatar_url,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        if (! $user->isActive()) {
            return $this->failure($portal, $user->isPendingApproval()
                ? 'Your application is awaiting Super Admin approval.'
                : 'This account is currently inactive. Please contact the editorial office.');
        }

        if (! $this->canAccessPortal($user, $portal)) {
            return $this->failure($portal, 'This Google account does not have access to that workspace.');
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route(PortalDestination::routeNameForPortal($portal))
            ->with('success', 'Welcome back, '.$user->name.'.');
    }

    /** @param array<string, mixed> $profile */
    private function createPendingApplicant(array $profile, string $email, string $googleId, string $role): User
    {
        return DB::transaction(function () use ($profile, $email, $googleId, $role): User {
            $name = Str::limit(trim((string) ($profile['name'] ?? Str::before($email, '@'))), 255, '');
            $user = User::query()->create([
                'name' => $name !== '' ? $name : Str::before($email, '@'),
                'email' => $email,
                'google_id' => $googleId,
                'google_avatar_url' => is_string($profile['picture'] ?? null) ? $profile['picture'] : null,
                'password' => Str::random(80),
                'email_verified_at' => now(),
                'status' => 'pending',
                'is_active' => false,
                'requested_role' => $role,
            ]);

            if (in_array($role, ['author', 'contributor'], true)) {
                Author::query()->create([
                    'user_id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_path' => null,
                    'is_active' => false,
                    'is_verified' => false,
                ]);
            }

            return $user;
        });
    }

    private function canAccessPortal(User $user, string $portal): bool
    {
        return match ($portal) {
            'author' => $user->hasRole('author'),
            'contributor' => $user->hasRole('contributor'),
            'editor' => $user->hasRole('editor'),
            'reviewer' => $user->hasRole('reviewer'),
            'admin' => $user->hasAnyRole('admin', 'super-admin'),
        };
    }

    private function failure(string $portal, string $message): RedirectResponse
    {
        return redirect()->route($portal.'.login')->withErrors(['email' => $message]);
    }

    public function oneTap(Request $request, string $portal, \App\Services\GoogleIdTokenVerifier $verifier): RedirectResponse
    {
        $this->assertEnabled();
        $request->validate(['credential' => ['required', 'string', 'max:16384']]);
        $challenge = $request->session()->pull('google_one_tap.'.$portal);
        if (!is_array($challenge) || ($challenge['expires'] ?? 0) < time()) {
            return $this->failure($portal, '[google_session_expired] Reload this page and try Google sign-in again.');
        }
        try {
            $profile = $verifier->verify($request->string('credential')->toString());
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return $this->failure($portal, $exception->errors()['google'][0]);
        } catch (\Throwable $exception) {
            report($exception);
            return $this->failure($portal, '[google_unavailable] Google sign-in is temporarily unavailable. Please try again.');
        }
        if (!is_string($profile['nonce'] ?? null) || !hash_equals($challenge['nonce'], $profile['nonce'])) {
            return $this->failure($portal, '[google_session_expired] This sign-in does not match your session. Reload and try again.');
        }
        return $this->complete($request, $portal, $profile);
    }

    private function assertEnabled(): void
    {
        abort_unless(
            config('services.google.enabled') && filled(config('services.google.client_id')) && filled(config('services.google.client_secret')),
            404,
        );
    }
}
