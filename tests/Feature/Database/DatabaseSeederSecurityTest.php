<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EditorialContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

final class DatabaseSeederSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_seeding_installs_baseline_data_without_demo_accounts_or_content(): void
    {
        config()->set('publication.seeding.demo_content', false);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('roles', ['slug' => 'super-admin']);
        $this->assertDatabaseHas('permissions', ['slug' => 'users.manage']);
        $this->assertDatabaseHas('settings', ['key' => 'site.name']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('articles', 0);
    }

    public function test_explicit_demo_seeding_uses_unrecoverable_random_passwords(): void
    {
        config()->set('publication.seeding.demo_content', true);

        $this->seed(DatabaseSeeder::class);

        $demoUsers = User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', ['author', 'editor', 'reviewer']))
            ->get();

        $this->assertCount(16, $demoUsers);
        foreach ($demoUsers as $user) {
            $this->assertFalse(Hash::check('password', $user->password));
        }
        $this->assertDatabaseCount('articles', 50);
    }

    public function test_explicit_admin_credentials_still_create_a_verified_super_administrator(): void
    {
        config()->set('publication.seeding.demo_content', false);
        $environment = [
            'SEED_ADMIN_NAME' => 'Release Administrator',
            'SEED_ADMIN_EMAIL' => 'release-admin@example.com',
            'SEED_ADMIN_PASSWORD' => 'Unshared!Admin42',
        ];

        try {
            foreach ($environment as $key => $value) {
                putenv("{$key}={$value}");
            }

            $this->seed(DatabaseSeeder::class);
        } finally {
            foreach (array_keys($environment) as $key) {
                putenv($key);
            }
        }

        $admin = User::query()->where('email', 'release-admin@example.com')->firstOrFail();

        $this->assertSame('Release Administrator', $admin->name);
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue($admin->hasRole('super-admin'));
        $this->assertTrue(Hash::check('Unshared!Admin42', $admin->password));
    }

    public function test_demo_seeder_refuses_to_run_outside_local_or_testing_environments(): void
    {
        config()->set('publication.seeding.demo_content', true);
        $this->app->instance('env', 'production');

        try {
            $this->app->make(EditorialContentSeeder::class)->run();
            $this->fail('Production demo seeding should have been rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Demo content may only be seeded in local or testing environments.', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
    }
}
