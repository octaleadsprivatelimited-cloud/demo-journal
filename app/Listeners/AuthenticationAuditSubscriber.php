<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Throwable;

final class AuthenticationAuditSubscriber
{
    public function __construct(private readonly AuditService $audit) {}

    public function login(Login $event): void
    {
        $this->safeRecord($event->user instanceof User ? $event->user : null, 'login_succeeded');
    }

    public function failed(Failed $event): void
    {
        $this->safeRecord(
            $event->user instanceof User ? $event->user : null,
            'login_failed',
            ['email' => mb_strtolower((string) ($event->credentials['email'] ?? ''))],
        );
    }

    public function logout(Logout $event): void
    {
        $this->safeRecord($event->user instanceof User ? $event->user : null, 'logout');
    }

    public function lockout(Lockout $event): void
    {
        $this->safeRecord(null, 'login_locked_out', [
            'email' => mb_strtolower((string) $event->request->input('email')),
        ]);
    }

    /** @param array<string, mixed> $context */
    private function safeRecord(?User $user, string $event, array $context = []): void
    {
        try {
            $this->audit->record(
                $user,
                $event,
                newValues: $context,
                actor: $user,
            );
        } catch (Throwable $exception) {
            Log::warning('Authentication audit could not be persisted.', [
                'event' => $event,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'login',
            Failed::class => 'failed',
            Logout::class => 'logout',
            Lockout::class => 'lockout',
        ];
    }
}
