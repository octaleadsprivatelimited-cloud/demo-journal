<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ArticleStatus;
use App\Events\ArticleStatusChanged;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

final class MailQueueReliabilityTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('permanentResponses')]
    public function test_permanent_smtp_rejections_are_saved_once_without_automatic_resends(int $code): void
    {
        $this->rejectingSmtp($code);
        User::factory()->unverified()->create()->sendEmailVerificationNotification();

        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'mail', '--once' => true, '--tries' => 3, '--backoff' => 60])->assertSuccessful();

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertDatabaseHas('failed_jobs', ['queue' => 'mail']);
    }

    public static function permanentResponses(): array
    {
        return ['unknown recipient' => [550], 'spam rejection' => [554]];
    }

    public function test_a_temporary_smtp_failure_remains_queued_with_a_delay(): void
    {
        $this->rejectingSmtp(451);
        User::factory()->unverified()->create()->sendEmailVerificationNotification();
        $before = time();

        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'mail', '--once' => true, '--tries' => 3, '--backoff' => 60])->assertSuccessful();

        $this->assertDatabaseCount('failed_jobs', 0);
        $job = DB::table('jobs')->sole();
        $this->assertSame(1, $job->attempts);
        $this->assertGreaterThanOrEqual($before + 60, $job->available_at);
    }

    public function test_the_scheduler_publishes_in_process_without_filling_the_mail_worker_queue_and_retains_held_failures(): void
    {
        Event::fake([ArticleStatusChanged::class]);
        $this->travelTo(now()->startOfDay());
        config(['queue.default' => 'database', 'queue.connections.database.connection' => 'sqlite']);
        $due = Article::factory()->create(['status' => ArticleStatus::Scheduled, 'scheduled_for' => now()->subMinute(), 'published_at' => null]);
        $future = Article::factory()->create(['status' => ArticleStatus::Scheduled, 'scheduled_for' => now()->addDay(), 'published_at' => null]);
        DB::table('failed_jobs')->insert(['uuid' => 'held-mail-fixture', 'connection' => 'database', 'queue' => 'mail-before-repair-20261014', 'payload' => 'held-original-payload', 'exception' => 'Older delivery failure', 'failed_at' => now()->subDays(10)]);

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame(ArticleStatus::Published, $due->fresh()->status);
        $this->assertSame(ArticleStatus::Scheduled, $future->fresh()->status);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseHas('failed_jobs', ['uuid' => 'held-mail-fixture', 'payload' => 'held-original-payload']);
    }

    private function rejectingSmtp(int $code): void
    {
        config(['queue.default' => 'database', 'queue.connections.database.connection' => 'sqlite', 'queue.failed.database' => 'sqlite', 'mail.default' => 'smtp-rejection-test', 'mail.mailers.smtp-rejection-test' => ['transport' => 'smtp-rejection-test']]);
        Mail::extend('smtp-rejection-test', static fn () => new class($code) extends AbstractTransport
        {
            public function __construct(private readonly int $code)
            {
                parent::__construct();
            }

            protected function doSend(SentMessage $message): void
            {
                throw new UnexpectedResponseException('SMTP rejection test', $this->code);
            }

            public function __toString(): string
            {
                return 'smtp-rejection-test';
            }
        });
    }
}
