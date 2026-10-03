<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PortalDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasVerifiedEmail()) {
            return redirect()->route(PortalDestination::routeNameForUser($user));
        }

        if (app()->isProduction() && (blank(config('mail.default')) || in_array(config('mail.default'), ['log', 'array'], true))) {
            return back()->withErrors(['email' => 'Email verification delivery is not available yet. Please contact the journal administrator.']);
        }

        $user?->sendEmailVerificationNotification();

        return back()->with('success', 'A fresh verification link has been queued for delivery to your email address.');
    }
}
