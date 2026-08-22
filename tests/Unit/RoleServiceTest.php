<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_and_permission_helpers_enforce_database_backed_rbac(): void
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'Editor', 'slug' => 'editor']);
        $permission = Permission::query()->create(['name' => 'Publish Articles', 'slug' => 'articles.publish', 'group' => 'articles']);
        $role->permissions()->attach($permission);

        app(RoleService::class)->assign($user, $role);
        $user->refresh()->load('roles');

        $this->assertTrue($user->hasAnyRole('editor', 'admin'));
        $this->assertTrue($user->hasPermission('articles.publish'));

        app(RoleService::class)->revoke($user, $role);
        $this->assertFalse($user->fresh()->load('roles')->hasRole('editor'));
    }
}
