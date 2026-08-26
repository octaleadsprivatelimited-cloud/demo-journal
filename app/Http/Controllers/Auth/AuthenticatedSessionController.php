<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\PublicationSettings;
use App\Support\PortalDestination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AuthenticatedSessionController extends Controller
{
    public function chooser(): View
    {
        return view('auth.login');
    }

    public function createAuthor(PublicationSettings $publicationSettings): View
    {
        return view('auth.author-login', [
            'authorRegistrationEnabled' => $publicationSettings->featureEnabled('author_registration'),
        ]);
    }

    public function createEditor(): View
    {
        return view('auth.editor-login');
    }

    public function createReviewer(): View
    {
        return view('auth.reviewer-login');
    }

    public function createAdmin(): View
    {
        return view('auth.admin-login');
    }
    public function createContributor(): View { return view('auth.contributor-login'); }

    public function storeAuthor(LoginRequest $request): RedirectResponse
    {
        return $this->storeForPortal($request, 'author');
    }

    public function storeEditor(LoginRequest $request): RedirectResponse
    {
        return $this->storeForPortal($request, 'editor');
    }

    public function storeReviewer(LoginRequest $request): RedirectResponse
    {
        return $this->storeForPortal($request, 'reviewer');
    }

    public function storeAdmin(LoginRequest $request): RedirectResponse
    {
        return $this->storeForPortal($request, 'admin');
    }
    public function storeContributor(LoginRequest $request): RedirectResponse { return $this->storeForPortal($request, 'contributor'); }

    private function storeForPortal(LoginRequest $request, string $portal): RedirectResponse
    {
        $user = $request->authenticateFor($portal);
        $request->session()->regenerate();

        return redirect()->route(PortalDestination::routeNameForPortal($portal))
            ->with('success', 'Welcome back, '.$user->name.'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been signed out securely.');
    }
}
