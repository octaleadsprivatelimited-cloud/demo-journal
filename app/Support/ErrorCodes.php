<?php

namespace App\Support;

final class ErrorCodes
{
    public static function forStatus(int $status): string
    {
        return match ($status) {
            400 => 'invalid_request', 401 => 'authentication_required', 403 => 'access_denied',
            404 => 'not_found', 405 => 'method_not_allowed', 409 => 'version_conflict',
            413 => 'upload_too_large', 419 => 'session_expired', 422 => 'validation_failed',
            429 => 'rate_limit_exceeded', 502, 503, 504 => 'service_unavailable',
            default => $status >= 500 ? 'server_error' : 'request_failed',
        };
    }

    public static function fromMessage(string $message): string
    {
        return preg_match('/^\[([a-z_]+)\]/', $message, $matches) ? $matches[1] : 'validation_failed';
    }
}
