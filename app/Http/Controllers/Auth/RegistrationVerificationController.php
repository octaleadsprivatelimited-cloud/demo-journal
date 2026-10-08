<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class RegistrationVerificationController extends Controller
{
    public function show(): View
    {
        return view('auth.registration-verification');
    }

    public function verify(string $id, string $hash): View
    {
        DB::transaction(function () use ($id, $hash): void {
            $user = User::query()->lockForUpdate()->findOrFail($id);
            abort_unless($user->isPendingApproval() || $user->isActive(), 403);
            abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

            if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
                event(new Verified($user));
            }
        });

        // Confirm ownership only. Approval, roles and the current session remain unchanged.
        return view('auth.registration-verification', ['emailVerified' => true]);
    }

    public function resend(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email:rfc', 'max:255']]);

        if (app()->isProduction() && (blank(config('mail.default')) || in_array(config('mail.default'), ['log', 'array'], true))) {
            return back()->withErrors(['email' => 'Email verification delivery is not available yet. Please contact the journal administrator.'])->withInput();
        }

        $user = User::query()->where('email', mb_strtolower($data['email']))->first();
        if ($user?->isPendingApproval() && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('success', 'If an unverified application matches this address, a fresh verification link has been queued. Approved accounts can request a link after signing in.');
    }
}
