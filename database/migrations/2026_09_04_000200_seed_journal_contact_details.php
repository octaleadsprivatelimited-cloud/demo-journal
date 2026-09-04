<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $values = [
            'contact.email' => 'info@larixjournals.com',
            'contact.additional_email' => 'alexia@larixjournals.com',
            'contact.editorial_email' => 'sjc.editorjournal@gmail.com',
            'contact.whatsapp' => '+65 8515 1080',
            'contact.address' => '10 Anson Road, International Plaza, #22-02, Singapore 079903',
            'journal.publisher_name' => 'Larix Journals',
            'journal.publisher_address' => '10 Anson Road, International Plaza, #22-02, Singapore 079903',
        ];

        foreach ($values as $key => $value) {
            $existing = DB::table('settings')->where('key', $key)->first();
            $current = $existing ? json_decode($existing->value, true) : null;

            // Populate missing/demo content while retaining later administrator edits.
            if ($current !== null && $current !== '' && $current !== 'editorial@example.com') {
                continue;
            }

            DB::table('settings')->updateOrInsert(['key' => $key], [
                'value' => json_encode($value),
                'group' => str_starts_with($key, 'journal.') ? 'publication' : 'general',
                'is_public' => true,
                'created_at' => $existing?->created_at ?? now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Contact content may have been edited after deployment; retain it on rollback.
    }
};
