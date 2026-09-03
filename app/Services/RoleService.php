<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RoleService
{
    public function assign(User $user, Role $role, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $role): void {
            $user->roles()->syncWithoutDetaching([
                $role->getKey() => ['assigned_at' => now()],
            ]);
        });
    }

    public function revoke(User $user, Role $role, ?User $actor = null): void
    {
        DB::transaction(function () use ($user, $role): void {
            $user->roles()->detach($role->getKey());
        });
    }
}
