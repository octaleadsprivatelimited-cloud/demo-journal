<?php

declare(strict_types=1);

return [
    'force_https' => env('APP_FORCE_HTTPS', false),
    'trusted_proxies' => env('TRUSTED_PROXIES'),
    'local_admin_bypass' => [
        'enabled' => env('LOCAL_ADMIN_BYPASS_ENABLED', false),
        'name' => env('LOCAL_ADMIN_NAME', 'Local Administrator'),
        'email' => env('LOCAL_ADMIN_EMAIL', 'local-admin@localhost.test'),
        'bind_address' => env('APP_BIND_ADDRESS', '127.0.0.1'),
        'allowed_remote_addresses' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('LOCAL_ADMIN_ALLOWED_REMOTE_ADDRS', '127.0.0.1,::1')),
        ))),
    ],
    'content_security_policy' => env(
        'CONTENT_SECURITY_POLICY',
        "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self' data:; frame-src https://www.youtube-nocookie.com https://player.vimeo.com; object-src 'none'; base-uri 'self'; form-action 'self'"
    ),
];
