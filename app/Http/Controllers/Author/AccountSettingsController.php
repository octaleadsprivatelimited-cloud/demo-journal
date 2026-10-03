<?php

declare(strict_types=1);

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Http\Requests\Author\AccountSettingsRequest;
use App\Services\CredentialRevocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class AccountSettingsController extends Controller
{
    public function edit(): View
    {
        return view('author.settings.edit');
    }

    public function update(AccountSettingsRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $request->user()->update(['password' => $request->validated('password')]);
            app(CredentialRevocation::class)->revoke($request->user(), $request->session()->getId());
        });
        $request->session()->regenerate();

        return back()->with('success', 'Password updated. Other sessions and API tokens have been revoked.');
    }
}
