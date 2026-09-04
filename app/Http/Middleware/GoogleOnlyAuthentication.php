<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GoogleOnlyAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        if (config('services.google.enabled') && config('services.google.only') && $request->isMethod('get')
            && $request->routeIs('password.request', 'password.reset', 'password.confirm')) {
            return redirect()->route('login');
        }
        if (config('services.google.enabled') && config('services.google.only') && $request->isMethod('post')
            && ($request->routeIs('*.login.store', '*.register.store', 'password.email', 'password.store', 'password.confirm.store'))) {
            throw ValidationException::withMessages(['google' => '[google_sign_in_required] Use Continue with Google. Password sign-in and signup are disabled.']);
        }
        return $next($request);
    }
}
