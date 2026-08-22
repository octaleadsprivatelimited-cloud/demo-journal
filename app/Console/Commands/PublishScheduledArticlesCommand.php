<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PublishScheduledArticles;
use Illuminate\Console\Command;

class PublishScheduledArticlesCommand extends Command
{
    protected $signature = 'articles:publish-scheduled {--sync : Publish due articles in the current process}';

    protected $description = 'Queue publication of all scheduled articles whose publication time has arrived';

    public function handle(): int
    {
        $this->option('sync')
            ? PublishScheduledArticles::dispatchSync()
            : PublishScheduledArticles::dispatch();

        $this->components->info('Scheduled publication job dispatched.');

        return self::SUCCESS;
    }
}
