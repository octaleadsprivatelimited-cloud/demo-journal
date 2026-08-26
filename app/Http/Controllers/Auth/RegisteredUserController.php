<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Author;
use App\Models\User;
use App\Services\PublicationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class RegisteredUserController extends Controller
{
    public function chooser(PublicationSettings $publicationSettings): View
    {
        return view('auth.register', [
            'authorRegistrationEnabled' => $publicationSettings->featureEnabled('author_registration'),
        ]);
    }

    public function createAuthor(): View
    {
        return view('auth.author-register');
    }

    public function createEditor(): View
    {
        return view('auth.editor-register');
    }

    public function createReviewer(): View
    {
        return view('auth.reviewer-register');
    }
    public function createContributor(): View { return view('auth.contributor-register'); }
    public function createAdmin(): View { return view('auth.admin-register'); }

    public function submitted(): View
    {
        return view('auth.application-submitted');
    }

    public function storeAuthor(RegisterRequest $request): RedirectResponse
    {
        return $this->storeForRole($request, 'author');
    }

    public function storeEditor(RegisterRequest $request): RedirectResponse
    {
        return $this->storeForRole($request, 'editor');
    }

    public function storeReviewer(RegisterRequest $request): RedirectResponse
    {
        return $this->storeForRole($request, 'reviewer');
    }
    public function storeContributor(RegisterRequest $request): RedirectResponse { return $this->storeForRole($request, 'contributor'); }
    public function storeAdmin(RegisterRequest $request): RedirectResponse { return $this->storeForRole($request, 'admin'); }

    private function storeForRole(RegisterRequest $request, string $requestedRole): RedirectResponse
    {
        $data = $request->validated();
        $avatarPath = in_array($requestedRole, ['author','contributor'], true)
            ? $request->file('profile_image')?->store('authors/avatars', 'public')
            : null;

        $user = DB::transaction(function () use ($data, $avatarPath, $requestedRole): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'phone' => $data['phone'] ?? null,
                'organization' => $data['organization'] ?? null,
                'designation' => $data['designation'] ?? null,
                'profile_image_path' => $avatarPath,
                'password' => $data['password'],
                'status' => 'pending',
                'is_active' => false,
                'requested_role' => $requestedRole,
            ]);

            if (in_array($requestedRole, ['author','contributor'], true)) {
                Author::query()->create([
                    'user_id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                    'biography' => $data['biography'] ?? null,
                    'designation' => $user->designation,
                    'organization' => $user->organization,
                    'avatar_path' => $avatarPath,
                    'is_active' => false,
                    'is_verified' => false,
                ]);
            }

            return $user;
        });

        return redirect()->route('registration.submitted')->with('application', [
            'email' => $user->email,
            'role' => Str::headline($requestedRole),
        ]);
    }
}
