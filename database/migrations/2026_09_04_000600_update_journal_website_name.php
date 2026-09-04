<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('settings')->where('key', 'site.name')->first();
        DB::table('settings')->updateOrInsert(['key' => 'site.name'], [
            'value' => json_encode('Singapore Journal of Cardiology'),
            'group' => 'general',
            'is_public' => true,
            'created_at' => $existing?->created_at ?? now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve the administrator's current publication name on rollback.
    }
};
