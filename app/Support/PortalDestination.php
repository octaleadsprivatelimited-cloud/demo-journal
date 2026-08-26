<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use InvalidArgumentException;

final class PortalDestination
{
    public static function routeNameForPortal(string $portal): string
    {
        return match ($portal) {
            'author' => 'author.dashboard',
            'contributor' => 'author.dashboard',
            'editor' => 'editor.dashboard',
            'reviewer' => 'reviewer.dashboard',
            'admin' => 'admin.dashboard',
            default => throw new InvalidArgumentException("Unsupported portal [{$portal}]."),
        };
    }

    public static function routeNameForUser(User $user): string
    {
        return match (true) {
            $user->hasAnyRole('super-admin', 'admin') => self::routeNameForPortal('admin'),
            $user->hasRole('editor') => self::routeNameForPortal('editor'),
            $user->hasRole('reviewer') => self::routeNameForPortal('reviewer'),
            $user->hasAnyRole('author', 'contributor') => self::routeNameForPortal('author'),
            default => 'home',
        };
    }
}
