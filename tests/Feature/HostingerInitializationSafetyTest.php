<?php

declare(strict_types=1);

namespace Tests\Feature;

use Symfony\Component\Process\Process;
use Tests\TestCase;

final class HostingerInitializationSafetyTest extends TestCase
{
    public function test_a_database_url_cannot_redirect_initialization_to_another_database(): void
    {
        $process = $this->runInitializer();

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('resolved database connection is not the intended SJNR preview database', $process->getOutput());
        $this->assertStringNotContainsString('migrate completed', $process->getOutput());
    }

    public function test_demo_or_admin_seeding_is_rejected_before_any_database_work(): void
    {
        foreach ([
            ['SEED_DEMO_CONTENT' => 'true'],
            ['SEED_ADMIN_EMAIL' => 'unrequested-admin@example.test'],
            ['SEED_ADMIN_PASSWORD' => 'AnUnrequestedSeedPassword123!'],
        ] as $overrides) {
            $process = $this->runInitializer($overrides);

            $this->assertSame(1, $process->getExitCode());
            $this->assertStringContainsString('initialization is disabled', $process->getOutput());
            $this->assertStringNotContainsString('migrate completed', $process->getOutput());
        }
    }

    private function runInitializer(array $overrides = []): Process
    {
        // The wrong database URL must be rejected before attempting a connection.
        $process = new Process([PHP_BINARY, base_path('scripts/hostinger-initialize-sjnr.php')], base_path(), [
            'JOURNAL_PROFILE' => 'sjnr',
            'APP_ENV' => 'testing',
            'APP_NAME' => 'Singapore Journal of Nursing Research',
            'APP_URL' => 'https://isolated-sjnr.hostingersite.com',
            'APP_PUBLIC_PATH' => '',
            'PUBLIC_DISK_ROOT' => '',
            'SJNR_INITIALIZE_ALLOWED' => 'true',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_DATABASE' => 'u709299470_sjnr_preview',
            'DB_URL' => 'mysql://unused:unused@127.0.0.1:3306/unrelated_journal',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'SEED_DEMO_CONTENT' => 'false',
            'SEED_ADMIN_EMAIL' => '',
            'SEED_ADMIN_PASSWORD' => '',
            ...$overrides,
        ]);
        $process->setTimeout(15);
        $process->run();

        return $process;
    }
}
