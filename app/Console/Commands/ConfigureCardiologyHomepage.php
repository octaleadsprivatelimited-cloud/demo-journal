<?php

namespace App\Console\Commands;

use App\Services\CardiologyHomepageSetup;
use Illuminate\Console\Command;

class ConfigureCardiologyHomepage extends Command
{
    protected $signature = 'journal:configure-cardiology-homepage {--apply : Apply the reviewed initial discipline and article placement changes}';

    protected $description = 'Preview or apply the requested SJC cardiology disciplines and two homepage selections';

    public function handle(CardiologyHomepageSetup $setup): int
    {
        $report = $setup->apply((bool) $this->option('apply'));
        unset($report['before']);
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
