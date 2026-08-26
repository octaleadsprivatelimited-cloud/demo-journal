<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use Closure;
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
    public function handle(Request $request, Closure $next): Response
    {
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

        if (! $this->shouldBypass($request)) {
            return $next($request);
        }

        if ($authenticatedUser instanceof User && $authenticatedUser->hasAnyRole('editor', 'admin', 'super-admin')) {
            return $next($request);
        }

        $role = Role::query()->firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Full access to every publication and system setting.',
                'is_system' => true,
            ],
        );

        $email = (string) config('security.local_admin_bypass.email', 'local-admin@localhost.test');
        $user = User::query()->where('email', $email)->first();

        if ($user && ! $user->is_local_admin_bypass) {
            Log::warning('Local administrator bypass refused an email collision.', ['email' => $email]);

            return $next($request);
        }

        if (! $user) {
            $user = new User;
            $user->forceFill([
                'name' => (string) config('security.local_admin_bypass.name', 'Local Administrator'),
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
        $user->setRelation('roles', collect([$role]));

        Auth::setUser($user);

        try {
            return $next($request);
        } finally {
            $this->deactivatePersistentIdentity($user);

            if ($authenticatedUser instanceof User) {
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

    private function shouldBypass(Request $request): bool
    {
        if (! app()->environment('local') || ! config('security.local_admin_bypass.enabled', false)) {
            return false;
        }

        if (! $request->is('admin', 'admin/*')) {
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
