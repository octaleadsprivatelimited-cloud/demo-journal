<?php

declare(strict_types=1);

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Http\Requests\Author\AccountSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class AccountSettingsController extends Controller
{
    public function edit(): View
    {
        return view('author.settings.edit');
    }

    public function update(AccountSettingsRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->input('password')]);
        $request->session()->regenerate();

        return back()->with('success', 'Password updated. Other remembered sessions will require authentication again.');
    }
}
