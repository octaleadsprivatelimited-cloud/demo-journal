<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole('super-admin', 'admin');
    }

    public function view(User $user, Setting $setting): bool
    {
        return $setting->is_public || $user->hasAnyRole('super-admin', 'admin');
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole('super-admin', 'admin');
    }

    public function update(User $user, Setting $setting): bool
    {
        return $user->hasAnyRole('super-admin', 'admin');
    }

    public function delete(User $user, Setting $setting): bool
    {
        return $user->hasRole('super-admin');
    }
}
