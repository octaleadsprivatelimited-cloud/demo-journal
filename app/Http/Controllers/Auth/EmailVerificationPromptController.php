<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PortalDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class EmailVerificationPromptController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        return $user instanceof User && $user->hasVerifiedEmail()
            ? redirect()->route(PortalDestination::routeNameForUser($user))
            : view('auth.verify-email');
    }
}
