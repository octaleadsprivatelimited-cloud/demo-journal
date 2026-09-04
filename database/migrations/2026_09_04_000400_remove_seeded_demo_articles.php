<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only the inspected original demo batch; new/adopted manuscripts are excluded.
        $demoOwners = DB::table('users')->whereIn('email', [
            'leopoldo42@example.net', 'champlin.granville@example.org', 'jerrod01@example.com',
            'nboyle@example.net', 'rodriguez.beatrice@example.com', 'eliezer48@example.net',
            'gregory75@example.com', 'vlang@example.net', 'kip.kassulke@example.com', 'muller.leda@example.org',
        ])->select('id');

        DB::table('articles')->whereBetween('id', [1, 50])
            ->where('created_at', '2026-09-04 08:35:34')
            ->whereIn('created_by_id', $demoOwners)
            ->whereNotIn('id', DB::table('manuscript_workflows')->select('article_id'))
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'updated_at' => now(), 'scheduled_for' => null]);
    }

    public function down(): void
    {
        // Keep the requested removal; the original records remain recoverable.
    }
};
