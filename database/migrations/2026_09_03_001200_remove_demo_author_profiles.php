<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove the author profiles generated with the demonstration editorial data.
     * This deliberately leaves application users, site settings, and policy pages
     * intact; only public contributor profiles are removed.
     */
    public function up(): void
    {
        DB::table('authors')->delete();
    }

    public function down(): void
    {
        // Deleted demonstration profiles are intentionally not restored.
    }
};
