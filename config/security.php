<?php

declare(strict_types=1);

return [
    'force_https' => env('APP_FORCE_HTTPS', false),
    'trusted_proxies' => env('TRUSTED_PROXIES'),
    'content_security_policy' => env(
        'CONTENT_SECURITY_POLICY',
        "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self' data:; frame-src https://www.youtube-nocookie.com https://player.vimeo.com; object-src 'none'; base-uri 'self'; form-action 'self'"
    ),
];
