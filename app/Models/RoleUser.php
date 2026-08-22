<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Relations\Pivot;

class RoleUser extends Pivot
{
    protected $table = 'role_user';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['role_id', 'user_id', 'assigned_at'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::created(function (self $pivot): void {
            $user = User::query()->find($pivot->user_id);
            $role = Role::query()->find($pivot->role_id);

            app(AuditService::class)->record(
                $user,
                'role_assigned',
                [],
                ['role_id' => $pivot->role_id, 'role' => $role?->slug],
                $role ? "Assigned {$role->name} role." : 'Assigned role.',
            );
        });

        static::deleted(function (self $pivot): void {
            $user = User::query()->find($pivot->user_id);
            $role = Role::query()->find($pivot->role_id);

            app(AuditService::class)->record(
                $user,
                'role_revoked',
                ['role_id' => $pivot->role_id, 'role' => $role?->slug],
                [],
                $role ? "Revoked {$role->name} role." : 'Revoked role.',
            );
        });
    }
}
