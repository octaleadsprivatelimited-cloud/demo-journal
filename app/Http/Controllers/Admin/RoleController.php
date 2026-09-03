<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class RoleController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        return view('admin.roles.index', ['roles' => Role::query()->withCount(['users', 'permissions'])->orderBy('name')->get()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Role::class);

        return view('admin.roles.create', ['permissions' => Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group')]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        Gate::authorize('create', Role::class);
        $role = Role::query()->create($request->safe()->except('permissions') + ['is_system' => false]);
        $role->permissions()->sync($request->input('permissions', []));

        return redirect()->route('admin.roles.edit', $role)->with('success', 'Role created.');
    }

    public function edit(Role $role): View
    {
        Gate::authorize('update', $role);

        return view('admin.roles.edit', ['role' => $role->load('permissions:id'), 'permissions' => Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group')]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        Gate::authorize('update', $role);
        $data = $request->safe()->except('permissions');
        if ($role->is_system) {
            unset($data['slug']);
        } $role->update($data);
        $role->permissions()->sync($request->input('permissions', []));

        return back()->with('success', 'Role permissions updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        Gate::authorize('delete', $role);
        abort_if($role->is_system || $role->users()->exists(), 409, 'System or assigned roles cannot be deleted.');
        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted.');
    }
}
