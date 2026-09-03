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

        $user?->sendEmailVerificationNotification();

        return back()->with('success', 'A fresh verification link has been sent to your email address.');
    }
}
