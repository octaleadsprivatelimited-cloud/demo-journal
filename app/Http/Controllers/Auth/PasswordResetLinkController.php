<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

final class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        if (app()->isProduction() && in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->withErrors(['email' => 'Email recovery is not available yet. Please contact the journal administrator.']);
        }
        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface|\Illuminate\Http\Client\ConnectionException $exception) {
            \Illuminate\Support\Facades\Log::warning('Password reset delivery unavailable.', ['error_type' => get_class($exception)]);
            return back()->withErrors(['email' => 'We could not send the reset email. Please try again later or contact the journal.']);
        }

        return back()->with('success', 'If an account matches that address, a password reset link has been sent.');
    }
}
