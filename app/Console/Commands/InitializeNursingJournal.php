<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class InitializeNursingJournal extends Command
{
    protected $signature = 'journal:initialize-sjnr
        {--apply : Initialize an unused SJNR database after migrations and baseline seeding}
        {--email= : Hostinger mailbox on sjnrjournal.com used for journal contact and alerts}';

    protected $description = 'Initialize SJNR branding and nursing disciplines without copying SJC users or manuscripts';

    public function handle(): int
    {
        if (config('journal.profile') !== 'sjnr') {
            $this->components->error('This command requires JOURNAL_PROFILE=sjnr. No data was changed.');

            return self::FAILURE;
        }

        $email = Str::lower(trim((string) ($this->option('email') ?: config('publication.contact_email'))));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || ! Str::endsWith($email, '@sjnrjournal.com')) {
            $this->components->error('Provide the intended Hostinger mailbox on sjnrjournal.com. No data was changed.');

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->components->info('Preview: initialize '.config('publication.name').' using '.$email.'.');
            $this->line('Only an unused database is eligible. Run with --apply after reviewing the target environment.');

            return self::SUCCESS;
        }

        return DB::transaction(function () use ($email): int {
            if (Setting::value('deployment.sjnr_initialized_at') !== null
                || DB::table('users')->exists()
                || DB::table('articles')->exists()
                || DB::table('workflow_files')->exists()) {
                $this->components->error('The database is already initialized or contains journal records. Use the administration portal to edit it. No data was changed.');

                return self::FAILURE;
            }

            $values = [
                'site.name' => config('publication.name'),
                'site.tagline' => config('publication.tagline'),
                'site.description' => config('publication.description'),
                'contact.email' => $email,
                'contact.additional_email' => null,
                'contact.editorial_email' => $email,
            ];
            foreach (config('journal.metadata', []) as $key => $value) {
                if (Setting::definition('journal.'.$key) !== null) {
                    $values['journal.'.$key] = $value;
                }
            }
            foreach ($values as $key => $value) {
                Setting::putDefined($key, $value);
            }

            foreach ([
                'nursing-research' => ['Nursing Research', 'Original research on nursing practice and healthcare.'],
                'clinical-nursing' => ['Clinical Nursing', 'Clinical studies and evidence for nursing practice.'],
                'patient-care' => ['Patient Care', 'Research on patient care, safety and outcomes.'],
                'nursing-education' => ['Nursing Education', 'Research on nursing education and professional development.'],
                'healthcare-systems' => ['Healthcare Systems', 'Research on healthcare delivery, management and services.'],
            ] as $slug => [$name, $description]) {
                Category::query()->firstOrCreate(['slug' => $slug], [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => true,
                ]);
            }

            Setting::put('deployment.sjnr_initialized_at', now()->toISOString(), 'deployment', false);
            $this->components->info('SJNR identity and nursing disciplines initialized. Mailbox provisioning and SMTP delivery must be verified separately.');

            return self::SUCCESS;
        });
    }
}
