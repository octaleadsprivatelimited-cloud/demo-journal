<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Author;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $avatarPath = $request->file('profile_image')?->store('authors/avatars', 'public');

        $user = DB::transaction(function () use ($data, $avatarPath): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'organization' => $data['organization'] ?? null,
                'designation' => $data['designation'] ?? null,
                'profile_image_path' => $avatarPath,
                'password' => $data['password'],
                'is_active' => true,
            ]);

            $role = Role::query()->firstOrCreate(
                ['slug' => 'author'],
                ['name' => 'Author', 'description' => 'Creates and manages original manuscripts.', 'is_system' => true],
            );
            $user->roles()->syncWithoutDetaching([$role->getKey()]);

            Author::query()->create([
                'user_id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'biography' => $data['biography'] ?? null,
                'designation' => $user->designation,
                'organization' => $user->organization,
                'avatar_path' => $avatarPath,
                'is_active' => true,
                'is_verified' => false,
            ]);

            return $user;
        });

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('verification.notice')
            ->with('success', 'Your author account is ready. Verify your email to submit work.');
    }
}
