<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use RuntimeException;
use Tests\TestCase;

class TestDatabaseGuardTest extends TestCase
{
    public function test_both_test_entry_points_refuse_a_non_test_database_before_booting(): void
    {
        $arguments = $_SERVER['argv'];
        $database = config('database.connections.sqlite.database');
        try {
            config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => 'journal']);
            foreach ([['artisan', 'test'], ['vendor/bin/phpunit'], ['vendor/bin/pest']] as $runner) {
                $_SERVER['argv'] = $runner;
                try {
                    (new AppServiceProvider($this->app))->register();
                    $this->fail('A test runner was allowed to use the journal database.');
                } catch (RuntimeException $exception) {
                    $this->assertStringContainsString('Refusing to run tests outside an isolated testing database', $exception->getMessage());
                }
            }
        } finally {
            $_SERVER['argv'] = $arguments;
            config(['database.connections.sqlite.database' => $database]);
        }
    }
}
