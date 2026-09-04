<?php

namespace App\Services;

use App\Models\Setting;

class WorkflowSettings
{
    public function values(): array
    {
        return array_replace_recursive(['manuscript_format' => config('workflow.manuscript_format'), 'article_format' => config('workflow.article_format'), 'deadlines' => config('workflow.deadlines'), 'notification_intro' => 'A manuscript workflow update is available.', 'doi_agency' => 'Manual registration'], Setting::where('key', 'workflow.configuration')->first()?->value ?? []);
    }

    public function apply(): void
    {
        foreach ($this->values() as $key => $value) {
            config(['workflow.'.$key => $value]);
        }
    }
}
