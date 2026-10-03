<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class CredentialRevocation
{
    public function revoke(User $user, ?string $preservedSessionId = null): void
    {
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->getKey())
            ->when($preservedSessionId !== null, fn ($query) => $query->where('id', '!=', $preservedSessionId))
            ->delete();
        $user->forceFill(['remember_token' => null])->saveQuietly();
        if ($preservedSessionId !== null && Auth::guard('web')->user()?->is($user)) {
            Auth::guard('web')->setUser($user);
        }
    }
}
