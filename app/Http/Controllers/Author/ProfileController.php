<?php

declare(strict_types=1);

namespace App\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Http\Requests\Author\ProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('author.profile.edit', ['author' => request()->user()->author()->first()]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $avatar = $request->file('avatar')?->store('authors/avatars', 'public');
        DB::transaction(function () use ($request, $data, $avatar): void {
            $emailChanged = $request->user()->email !== $data['email'];
            $request->user()->update(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null, 'organization' => $data['organization'] ?? null,
                'designation' => $data['designation'] ?? null, 'profile_image_path' => $avatar ?: $request->user()->profile_image_path, 'email_verified_at' => $emailChanged ? null : $request->user()->email_verified_at]);
            $existingAuthor = $request->user()->author()->first();
            $request->user()->author()->updateOrCreate([], ['name' => $data['name'], 'email' => $data['email'], 'organization' => $data['organization'] ?? null,
                'designation' => $data['designation'] ?? null, 'biography' => $data['biography'] ?? null, 'website_url' => $data['website_url'] ?? null, 'affiliation' => $data['affiliation'] ?? null, 'orcid' => $data['orcid'] ?? null,
                'avatar_path' => $avatar ?: $existingAuthor?->avatar_path, 'is_active' => true]);
        });
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('success', 'Profile updated. Verify your new email address.');
        }

        return back()->with('success', 'Public author profile updated.');
    }
}
