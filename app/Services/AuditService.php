<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditService
{
    /** @param array<string, mixed> $oldValues @param array<string, mixed> $newValues */
    public function record(
        ?Model $auditable,
        string $event,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
        ?User $actor = null,
    ): AuditLog {
        $request = app()->bound('request') ? request() : null;

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey() ?? auth()->id(),
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'event' => $event,
            'description' => $description,
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 1000, ''),
            'request_id' => $request?->headers->get('X-Request-ID'),
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    private function sanitize(array $values): array
    {
        $values = Arr::except($values, ['password', 'remember_token', 'token']);

        return array_map(static function (mixed $value): mixed {
            if (is_string($value) && mb_strlen($value) > 10_000) {
                return mb_substr($value, 0, 10_000).'…';
            }

            return $value;
        }, $values);
    }
}
