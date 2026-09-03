<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Author;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Registered;
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

        $baseQuery = User::query()->where('is_local_admin_bypass', false);

        return view('admin.users.index', [
            'applications' => (clone $baseQuery)->with('approver:id,name')->where('status', 'pending')->whereNotNull('requested_role')->oldest()->get(),
            'users' => $baseQuery->where('status', '!=', 'pending')->with(['roles:id,name,slug', 'author:id,user_id,is_verified,is_active'])->when($request->filled('q'), function ($q) use ($request): void {
            $term = '%'.addcslashes($request->string('q')->toString(), '%_').'%';
            $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('email', 'like', $term));
        })->when($request->filled('role'), fn ($q) => $q->where(fn ($rolesOrRequest) => $rolesOrRequest
            ->where('requested_role', $request->input('role'))
            ->orWhereHas('roles', fn ($roles) => $roles->where('slug', $request->input('role')))
        ))->latest()->paginate(25)->withQueryString(),
            'roles' => Role::query()->orderBy('name')->get(),
        ]);
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
        $this->assertManageableUser($user);

        return view('admin.users.edit', ['managedUser' => $user->load(['roles:id', 'author']), 'roles' => Role::query()->orderBy('name')->get()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);
        $this->assertManageableUser($user);
        $this->assertRoleChangesAllowed($request, $user);
        if ($user->isPendingApproval() && ($request->boolean('is_active') || $request->input('roles', []) !== [])) {
            throw ValidationException::withMessages(['is_active' => 'Use Approve application to activate this account and grant its requested role.']);
        }
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
        $this->assertManageableUser($user);
        abort_if($request->user()->is($user), 409, 'You cannot suspend your own account.');
        if ($user->hasRole('super-admin') && User::query()->whereHas('roles', fn ($query) => $query->where('slug', 'super-admin'))->where('is_active', true)->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'The last active super administrator cannot be suspended.']);
        }
        $user->update(['is_active' => false]);
        $user->author()->update(['is_active' => false]);

        return redirect()->route('admin.users.index')->with('success', 'Account suspended. Records and audit history were preserved.');
    }

    public function approve(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        Gate::authorize('approve', $user);
        $this->assertManageableUser($user);

        $application = DB::transaction(function () use ($request, $user, $audit): User {
            $application = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $application->isPendingApproval()) {
                throw ValidationException::withMessages(['application' => 'Only pending account applications can be approved.']);
            }

            $role = Role::query()->whereIn('slug', ['author', 'editor', 'admin'])->where('slug', $application->requested_role)->first();

            if (! $role) {
                throw ValidationException::withMessages(['application' => 'This application requests an unsupported role.']);
            }

            $application->roles()->sync([$role->getKey() => ['assigned_at' => now()]]);
            $application->forceFill([
                'status' => 'active',
                'is_active' => true,
                'approved_by_id' => $request->user()->getKey(),
                'approved_at' => now(),
                'rejected_at' => null,
            ])->save();
            $application->author()->update(['is_active' => true]);

            $audit->record(
                $application,
                'account_application_approved',
                ['status' => 'pending', 'requested_role' => $application->requested_role],
                ['status' => 'active', 'role' => $role->slug],
                "Approved {$role->name} account application.",
                $request->user(),
            );

            return $application;
        });

        if (! $application->hasVerifiedEmail()) {
            event(new Registered($application));
        }

        return back()->with('success', "{$application->name}'s {$application->requested_role} application was approved. A verification email has been sent.");
    }

    public function reject(Request $request, User $user, AuditService $audit): RedirectResponse
    {
        Gate::authorize('reject', $user);
        $this->assertManageableUser($user);

        DB::transaction(function () use ($request, $user, $audit): void {
            $application = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if (! $application->isPendingApproval()) {
                throw ValidationException::withMessages(['application' => 'Only pending account applications can be rejected.']);
            }

            $application->roles()->detach();
            $application->forceFill([
                'status' => 'rejected',
                'is_active' => false,
                'approved_by_id' => $request->user()->getKey(),
                'approved_at' => null,
                'rejected_at' => now(),
            ])->save();
            $application->author()->update(['is_active' => false]);

            $audit->record(
                $application,
                'account_application_rejected',
                ['status' => 'pending', 'requested_role' => $application->requested_role],
                ['status' => 'rejected'],
                'Rejected account application.',
                $request->user(),
            );
        });

        return back()->with('success', "{$user->name}'s application was rejected.");
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

    private function assertManageableUser(User $user): void
    {
        abort_if($user->is_local_admin_bypass, 404);
    }
}
