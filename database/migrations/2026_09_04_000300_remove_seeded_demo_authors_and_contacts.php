<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Match the inspected initial seed batch, never arbitrary example-domain accounts.
        $records = [
            'authors' => [
                'maryse.feest@example.org', 'nicolette.okon@example.com', 'mavis.rempel@example.net',
                'murphy.hubert@example.com', 'eschmeler@example.org', 'grant.rosetta@example.com',
                'eliza92@example.org', 'katrina56@example.net', 'yadira.carroll@example.org', 'karlie.corwin@example.com',
            ],
            'contact_submissions' => [
                'lynch.ashleigh@example.com', 'hal22@example.org', 'ddare@example.com', 'oreilly.bryon@example.net',
                'ferne33@example.net', 'camryn76@example.net', 'diamond98@example.com', 'benny.cormier@example.org',
                'haley.andreanne@example.org', 'mandy.goldner@example.net', 'ethyl.renner@example.org', 'ambrose64@example.com',
            ],
        ];

        foreach ($records as $table => $emails) {
            DB::table($table)->whereIn('email', $emails)
                ->whereBetween('created_at', ['2026-09-04 08:35:28', '2026-09-04 08:35:34'])
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Retain the requested removal; records remain recoverable through deleted_at.
    }
};
