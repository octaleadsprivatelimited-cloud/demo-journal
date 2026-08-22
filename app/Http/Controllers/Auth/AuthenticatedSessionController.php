<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();
        $destination = match (true) {
            $user?->hasAnyRole('super-admin', 'admin', 'editor') => route('admin.dashboard'),
            $user?->hasRole('reviewer') => route('reviewer.dashboard'),
            $user?->hasRole('author') => route('author.dashboard'),
            default => route('home'),
        };

        return redirect()->intended($destination)->with('success', 'Welcome back, '.$user?->name.'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been signed out securely.');
    }
}
