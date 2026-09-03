<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingSeeder::class,
        ]);

        if (! config('publication.seeding.demo_content', false)) {
            $this->command?->info('Demo content seeding skipped; set SEED_DEMO_CONTENT=true in a local or test environment to opt in.');

            return;
        }

        $this->call(EditorialContentSeeder::class);
    }
}
