<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Author;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        return view('admin.users.index', ['users' => User::query()->with(['roles:id,name,slug', 'author:id,user_id,is_verified,is_active'])->when($request->filled('q'), function ($q) use ($request): void {
            $term = '%'.addcslashes($request->string('q')->toString(), '%_').'%';
            $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('email', 'like', $term));
        })->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($roles) => $roles->where('slug', $request->input('role'))))->latest()->paginate(25)->withQueryString(), 'roles' => Role::query()->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.users.create', ['roles' => Role::query()->orderBy('name')->get()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);
        $this->assertRoleChangesAllowed($request, null);
        $user = DB::transaction(function () use ($request): User {
            $user = User::query()->create($request->safe()->except(['roles', 'email_verified', 'biography', 'author_verified', 'password_confirmation']) + [
                'email_verified_at' => $request->boolean('email_verified') ? now() : null, 'is_active' => $request->boolean('is_active'),
            ]);
            $user->roles()->sync($request->input('roles', []));
            $this->syncAuthor($user, $request);

            return $user;
        });

        return redirect()->route('admin.users.edit', $user)->with('success', 'User created.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('admin.users.edit', ['managedUser' => $user->load(['roles:id', 'author']), 'roles' => Role::query()->orderBy('name')->get()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $this->assertRoleChangesAllowed($request, $user);
        if ($request->user()->is($user) && ! $request->boolean('is_active')) {
            throw ValidationException::withMessages(['is_active' => 'You cannot suspend your own account.']);
        }
        DB::transaction(function () use ($request, $user): void {
            $data = $request->safe()->except(['roles', 'email_verified', 'biography', 'author_verified', 'password_confirmation']);
            if (blank($data['password'] ?? null)) {
                unset($data['password']);
            }
            $data['is_active'] = $request->boolean('is_active');
            $data['email_verified_at'] = $request->boolean('email_verified') ? ($user->email_verified_at ?? now()) : null;
            $user->update($data);
            $user->roles()->sync($request->input('roles', []));
            $this->syncAuthor($user, $request);
        });

        return back()->with('success', 'Account, profile, and role assignments updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);
        abort_if($request->user()->is($user), 409, 'You cannot suspend your own account.');
        if ($user->hasRole('super-admin') && User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super-admin'))->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'The last active super administrator cannot be suspended.']);
        }
        $user->update(['is_active' => false]);
        $user->author()->update(['is_active' => false]);

        return redirect()->route('admin.users.index')->with('success', 'Account suspended. Records and audit history were preserved.');
    }

    private function syncAuthor(User $user, UserRequest $request): void
    {
        if (! $user->roles()->where('slug', 'author')->exists() && ! $user->author()->exists()) {
            return;
        }
        Author::query()->updateOrCreate(['user_id' => $user->getKey()], [
            'name' => $user->name, 'email' => $user->email, 'designation' => $user->designation, 'organization' => $user->organization,
            'biography' => $request->input('biography'), 'is_verified' => $request->boolean('author_verified'), 'is_active' => $user->is_active,
        ]);
    }

    private function assertRoleChangesAllowed(UserRequest $request, ?User $managedUser): void
    {
        $superAdminRoleId = Role::query()->where('slug', 'super-admin')->value('id');
        $submittedRoles = array_map('intval', $request->input('roles', []));
        $assigningSuperAdmin = $superAdminRoleId && in_array((int) $superAdminRoleId, $submittedRoles, true);
        $managedIsSuperAdmin = $managedUser?->hasRole('super-admin') ?? false;

        if (($assigningSuperAdmin || $managedIsSuperAdmin) && ! $request->user()->hasRole('super-admin')) {
            throw ValidationException::withMessages(['roles' => 'Only a super administrator may assign, remove, or manage the super-admin role.']);
        }

        if ($managedIsSuperAdmin && ! $assigningSuperAdmin) {
            $activeSuperAdmins = User::query()->where('is_active', true)->whereHas('roles', fn ($query) => $query->where('slug', 'super-admin'))->count();
            if ($activeSuperAdmins <= 1) {
                throw ValidationException::withMessages(['roles' => 'The final active super administrator cannot be demoted.']);
            }
        }
    }
}
