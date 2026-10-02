<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('reviewer.profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'organization' => ['nullable', 'string', 'max:160'],
            'designation' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'department' => ['nullable', 'string', 'max:160'],
            'country' => ['nullable', 'string', 'max:100'],
            'expertise' => ['nullable', 'string', 'max:1000'],
            'research_interests' => ['nullable', 'string', 'max:2000'],
            'orcid' => ['nullable', 'regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/'],
        ]);
        DB::transaction(function () use ($request, $data) {
            $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
            $user->update(collect($data)->only(['name', 'organization', 'designation', 'phone'])->all() + [
                'reviewer_profile' => array_replace($user->reviewer_profile ?? [], collect($data)->only(['department', 'country', 'expertise', 'research_interests', 'orcid'])->all()),
            ]);
        });

        return back()->with('success', 'Reviewer profile updated.');
    }

    public function settings(Request $request)
    {
        return view('reviewer.settings', ['user' => $request->user()]);
    }

    public function preferences(Request $request)
    {
        $data = $request->validate(['available' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $data) {
            $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
            $user->update(['reviewer_profile' => array_replace($user->reviewer_profile ?? [], ['available' => (bool) $data['available']])]);
        });

        return back()->with('success', 'Review availability updated.');
    }

    public function password(Request $request)
    {
        abort_if($request->user()->is_local_admin_bypass || (config('services.google.enabled') && config('services.google.only')), 403);
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        $request->user()->update(['password' => Hash::make($data['password']), 'remember_token' => null]);
        $request->session()->regenerate();

        return back()->with('success', 'Password updated.');
    }
}
