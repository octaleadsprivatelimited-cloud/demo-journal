<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'site.name')->update(['value' => 'Singapore Journal of Cardiology', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'site.name')->update(['value' => 'octaleads Journal', 'updated_at' => now()]);
    }
};
