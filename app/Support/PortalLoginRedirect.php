<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Throwable;

final class PortalLoginRedirect
{
    public static function forPortal(Request $request, User $user, string $portal): RedirectResponse
    {
        $request->session()->put('password_hash_web', Auth::guard('web')->hashPasswordForCookie($user->getAuthPassword()));
        $intended = $request->session()->pull('url.intended');

        if (self::isCurrentAccountVerificationUrl($intended, $user)) {
            return redirect()->to($intended);
        }

        return redirect()->route($user->hasVerifiedEmail()
            ? PortalDestination::routeNameForPortal($portal)
            : 'verification.notice');
    }

    private static function isCurrentAccountVerificationUrl(mixed $url, User $user): bool
    {
        if (! is_string($url) || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return false;
        }

        try {
            $candidate = parse_url($url);
            $expected = parse_url(route('verification.verify', [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]));

            if (! is_array($candidate) || ! is_array($expected)
                || isset($candidate['user']) || isset($candidate['pass']) || isset($candidate['fragment'])) {
                return false;
            }

            foreach (['scheme', 'host', 'port', 'path'] as $part) {
                if (($candidate[$part] ?? null) !== ($expected[$part] ?? null)) {
                    return false;
                }
            }

            return URL::hasValidSignature(Request::create($url));
        } catch (Throwable) {
            return false;
        }
    }
}
