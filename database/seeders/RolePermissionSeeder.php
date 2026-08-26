<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = collect([
            'super-admin' => 'Super Admin',
            'admin' => 'Admin',
            'editor' => 'Editor',
            'reviewer' => 'Reviewer',
            'author' => 'Author',
            'contributor' => 'Contributor',
            'user' => 'User',
        ])->mapWithKeys(fn (string $name, string $slug) => [
            $slug => Role::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'description' => $name.' platform role', 'is_system' => true],
            ),
        ]);

        $permissions = [
            'dashboard.view', 'articles.view', 'articles.view-unpublished', 'articles.create',
            'articles.update', 'articles.update-any', 'articles.delete', 'articles.submit',
            'articles.review', 'articles.publish', 'authors.manage', 'categories.manage',
            'categories.delete', 'tags.manage', 'tags.delete', 'media.manage', 'comments.moderate',
            'contacts.manage', 'newsletter.manage', 'users.manage', 'roles.manage',
            'settings.manage', 'audit.view', 'analytics.view',
        ];

        $permissionModels = collect($permissions)->mapWithKeys(function (string $slug): array {
            [$group] = explode('.', $slug);

            return [$slug => Permission::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => str($slug)->replace('.', ' ')->title(), 'group' => $group],
            )];
        });

        $roles['super-admin']->permissions()->sync($permissionModels->pluck('id')->all());
        $roles['admin']->permissions()->sync($permissionModels->only([
            'dashboard.view', 'articles.view', 'articles.view-unpublished', 'articles.create',
            'articles.update', 'articles.update-any', 'articles.delete', 'articles.review',
            'articles.publish', 'authors.manage', 'categories.manage', 'categories.delete',
            'tags.manage', 'tags.delete', 'media.manage', 'comments.moderate', 'contacts.manage',
            'newsletter.manage', 'audit.view', 'analytics.view',
        ])->pluck('id')->all());
        $roles['editor']->permissions()->sync($permissionModels->only([
            'dashboard.view', 'articles.view', 'articles.view-unpublished', 'articles.create',
            'articles.update', 'articles.update-any', 'articles.review', 'articles.publish',
            'authors.manage', 'categories.manage', 'tags.manage', 'media.manage',
            'comments.moderate', 'analytics.view',
        ])->pluck('id')->all());
        $roles['reviewer']->permissions()->sync($permissionModels->only(['dashboard.view', 'articles.view', 'articles.view-unpublished', 'articles.review', 'media.manage'])->pluck('id')->all());
        $roles['author']->permissions()->sync($permissionModels->only(['dashboard.view', 'articles.view', 'articles.create', 'articles.update', 'articles.submit', 'media.manage'])->pluck('id')->all());
        $roles['contributor']->permissions()->sync($permissionModels->only(['dashboard.view', 'articles.view', 'articles.create', 'articles.update', 'articles.submit', 'media.manage'])->pluck('id')->all());
        $roles['user']->permissions()->sync($permissionModels->only(['articles.view'])->pluck('id')->all());

        $email = trim((string) (getenv('SEED_ADMIN_EMAIL') ?: env('SEED_ADMIN_EMAIL')));
        $password = (string) (getenv('SEED_ADMIN_PASSWORD') ?: env('SEED_ADMIN_PASSWORD'));

        if ($email === '' && $password === '') {
            $this->command?->info('Admin seeding skipped; set both SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD to opt in.');

            return;
        }

        if ($email === '' || $password === '') {
            throw new RuntimeException('Both SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD must be set to seed an administrator.');
        }

        if (mb_strlen($password) < 12) {
            throw new RuntimeException('SEED_ADMIN_PASSWORD must contain at least 12 characters.');
        }

        $admin = User::query()->updateOrCreate(
            ['email' => mb_strtolower($email)],
            [
                'name' => (string) (getenv('SEED_ADMIN_NAME') ?: env('SEED_ADMIN_NAME') ?: 'Platform Administrator'),
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'status' => 'active',
                'is_active' => true,
            ],
        );
        $admin->roles()->syncWithoutDetaching([$roles['super-admin']->getKey() => ['assigned_at' => now()]]);
    }
}
