<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Clear the initial demonstration manuscripts. Article-linked tables use
     * foreign-key cascades (or null-on-delete), so page settings, contacts,
     * newsletter records, and static informational pages are untouched.
     */
    public function up(): void
    {
        DB::table('articles')->delete();
    }

    public function down(): void
    {
        // Deleted demonstration data is intentionally not restored.
    }
};
