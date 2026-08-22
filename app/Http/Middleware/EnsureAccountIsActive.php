<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'isActive') && ! $user->isActive()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This account is not currently active.',
                ], Response::HTTP_FORBIDDEN);
            }

            auth()->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->withErrors([
                'email' => 'This account is not currently active. Contact the editorial office for assistance.',
            ]);
        }

        return $next($request);
    }
}
