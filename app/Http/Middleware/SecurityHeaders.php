<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $incomingRequestId = (string) $request->headers->get('X-Request-ID');
        $requestId = preg_match('/^[A-Za-z0-9._:-]{8,64}$/', $incomingRequestId)
            ? $incomingRequestId
            : (string) Str::uuid();
        $request->headers->set('X-Request-ID', $requestId);

        $response = $next($request);

        $response->headers->set('X-Request-ID', $requestId);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $policy = (string) config('security.content_security_policy');
        if (config('services.google.enabled')) {
            $directives = [];
            foreach (explode(';', $policy) as $directive) {
                $parts = preg_split('/\s+/', trim($directive));
                $name = array_shift($parts);
                if ($name) $directives[$name] = $parts;
            }
            foreach (['script-src', 'style-src', 'frame-src', 'connect-src'] as $name) {
                $sources = $directives[$name] ?? $directives['default-src'] ?? ["'self'"];
                $directives[$name] = array_unique(array_merge(array_diff($sources, ["'none'"]), ['https://accounts.google.com/gsi/']));
            }
            $policy = implode('; ', array_map(fn ($name, $sources) => $name.' '.implode(' ', $sources), array_keys($directives), $directives));
        }
        $response->headers->set('Cross-Origin-Opener-Policy', config('services.google.enabled') ? 'same-origin-allow-popups' : 'same-origin');
        $response->headers->set('Content-Security-Policy', $policy);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->user()) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
