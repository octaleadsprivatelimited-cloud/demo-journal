<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'contact.email'],
            ['value' => json_encode('info@larixjournals.com'), 'group' => 'general', 'is_public' => true, 'updated_at' => now(), 'created_at' => now()],
        );
        DB::table('settings')->updateOrInsert(
            ['key' => 'contact.phone'],
            ['value' => json_encode('+65 8515 1080'), 'group' => 'general', 'is_public' => true, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void {}
};
