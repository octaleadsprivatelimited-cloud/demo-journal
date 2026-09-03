<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\PortalDestination;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

final class VerifyEmailController extends Controller
{
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail() && $request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->route(PortalDestination::routeNameForUser($request->user()))
            ->with('success', 'Email verified. Your workspace is ready.');
    }
}
