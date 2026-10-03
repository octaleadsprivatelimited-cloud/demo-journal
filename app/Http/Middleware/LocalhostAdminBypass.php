<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

final class LocalhostAdminBypass
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $portal = 'admin'): Response
    {
        $sharedWorkflow = $portal === 'workflow';
        if ($sharedWorkflow) {
            $portal = (string) $request->session()->get('local_portal', '');
            if (! in_array($portal, ['admin', 'author', 'editor', 'reviewer'], true)) {
                return $next($request);
            }
        }
        abort_unless(in_array($portal, ['admin', 'author', 'editor', 'reviewer'], true), 404);
        $config = 'security.local_'.$portal.'_bypass';
        $authenticatedUser = Auth::user();

        if ($authenticatedUser instanceof User && $authenticatedUser->is_local_admin_bypass) {
            Auth::logout();
            $this->deactivatePersistentIdentity($authenticatedUser);

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            $authenticatedUser = null;
        }

        if (! $this->shouldBypass($request, $portal)) {
            return $next($request);
        }

        if ($authenticatedUser instanceof User && $authenticatedUser->hasAnyRole(...(match ($portal) {
            'author' => ['author', 'contributor'], 'editor' => ['editor'], 'reviewer' => ['reviewer', 'editor', 'admin', 'super-admin'], default => ['editor', 'admin', 'super-admin']
        }))) {
            return $next($request);
        }

        $request->session()->put('local_portal', $portal);

        $role = Role::query()->firstOrCreate(
            ['slug' => $portal === 'admin' ? 'super-admin' : $portal],
            [
                'name' => $portal === 'admin' ? 'Super Admin' : ucfirst($portal),
                'description' => 'Local '.$portal.' portal role.',
                'is_system' => true,
            ],
        );

        $email = (string) config($config.'.email', 'local-'.$portal.'@localhost.test');
        $user = User::query()->where('email', $email)->first();

        if ($user && ! $user->is_local_admin_bypass) {
            Log::warning('Local administrator bypass refused an email collision.', ['email' => $email]);

            return $next($request);
        }

        if (! $user) {
            $user = new User;
            $user->forceFill([
                'name' => (string) config($config.'.name', 'Local '.ucfirst($portal)),
                'email' => $email,
                'password' => Str::password(64),
                'email_verified_at' => null,
                'status' => 'inactive',
                'is_active' => false,
                'is_local_admin_bypass' => true,
            ]);
            $user->save();
        }

        $this->deactivatePersistentIdentity($user);
        $user->forceFill([
            'email_verified_at' => now(),
            'status' => 'active',
            'is_active' => true,
        ]);
        $user->setRelation('roles', new Collection([$role]));

        $passwordHashKey = 'password_hash_'.Auth::getDefaultDriver();
        $hadPasswordHash = $request->session()->has($passwordHashKey);
        $passwordHash = $request->session()->get($passwordHashKey);
        $request->session()->forget($passwordHashKey);
        Auth::setUser($user);

        try {
            return $next($request);
        } finally {
            $this->deactivatePersistentIdentity($user);
            $request->session()->forget($passwordHashKey);

            if ($authenticatedUser instanceof User) {
                if ($hadPasswordHash) {
                    $request->session()->put($passwordHashKey, $passwordHash);
                }
                Auth::setUser($authenticatedUser);
            } else {
                Auth::forgetUser();
            }
        }
    }

    private function deactivatePersistentIdentity(User $user): void
    {
        if (! $user->is_local_admin_bypass) {
            return;
        }

        $user->roles()->detach();
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        $user->forceFill([
            'email_verified_at' => null,
            'remember_token' => null,
            'status' => 'inactive',
            'is_active' => false,
        ])->saveQuietly();
        $user->unsetRelation('roles');
    }

    private function requestHost(Request $request): string
    {
        $authority = mb_strtolower(trim((string) $request->server('HTTP_HOST', '')));
        $host = parse_url('http://'.$authority, PHP_URL_HOST);

        return is_string($host) ? trim($host, '[]') : '';
    }

    private function shouldBypass(Request $request, string $portal): bool
    {
        if (! app()->environment('local') || ! config('security.local_'.$portal.'_bypass.enabled', false)) {
            return false;
        }

        if (! $request->is($portal, $portal.'/*', 'workflow', 'workflow/*')) {
            return false;
        }

        if (! in_array((string) config('security.local_admin_bypass.bind_address'), [
            '127.0.0.1',
            '::1',
            '[::1]',
        ], true)) {
            return false;
        }

        if (! in_array($this->requestHost($request), [
            'localhost',
            '127.0.0.1',
            '::1',
        ], true)) {
            return false;
        }

        $remoteAddress = (string) $request->server('REMOTE_ADDR', '');
        $allowedRemoteAddresses = (array) config('security.local_admin_bypass.allowed_remote_addresses', []);

        return $remoteAddress !== '' && IpUtils::checkIp($remoteAddress, $allowedRemoteAddresses);
    }
}
