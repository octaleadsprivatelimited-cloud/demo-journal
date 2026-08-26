<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'google_id', 'phone', 'profile_image_path', 'google_avatar_url', 'organization', 'designation',
        'password', 'email_verified_at', 'status', 'is_active', 'requested_role',
        'approved_by_id', 'approved_at', 'rejected_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_local_admin_bypass' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approved_by_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->using(RoleUser::class)->withPivot('assigned_at');
    }

    public function author(): HasOne
    {
        return $this->hasOne(Author::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'created_by_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    public function hasRole(string ...$roles): bool
    {
        $roles = array_map(static fn (string $role): string => str($role)->slug()->toString(), $roles);

        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn (Role $role): bool => in_array($role->slug, $roles, true));
        }

        return $this->roles()->whereIn('slug', $roles)->exists();
    }

    public function hasAnyRole(string|array ...$roles): bool
    {
        $flattened = [];

        foreach ($roles as $role) {
            array_push($flattened, ...(is_array($role) ? $role : [$role]));
        }

        return $this->hasRole(...$flattened);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->is_active;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending' && filled($this->requested_role);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }

        $permission = mb_strtolower(trim($permission));

        return $this->roles()->whereHas('permissions', fn (Builder $query) => $query->where('slug', $permission))->exists();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('is_active', true);
    }
}
