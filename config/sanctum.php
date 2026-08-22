<?php

declare(strict_types=1);

use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Http\Middleware\EncryptCookies;
use Laravel\Sanctum\Http\Middleware\ValidateCsrfToken;

return [
    'stateful' => array_filter(array_map(
        'trim',
        explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', 'localhost,localhost:8080,127.0.0.1,127.0.0.1:8080')),
    )),
    'guard' => ['web'],
    'expiration' => env('SANCTUM_EXPIRATION', 10080),
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'journal_'),
    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
