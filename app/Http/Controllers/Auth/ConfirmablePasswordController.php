<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmPasswordRequest;
use App\Models\User;
use App\Support\PortalDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class ConfirmablePasswordController extends Controller
{
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    public function store(ConfirmPasswordRequest $request): RedirectResponse
    {
        $request->session()->passwordConfirmed();
        $user = $request->user();

        return redirect()->intended(route(
            $user instanceof User ? PortalDestination::routeNameForUser($user) : 'home',
        ));
    }
}
