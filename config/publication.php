<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'octaleads Journal'),
    'tagline' => env('PUBLICATION_TAGLINE', 'Ideas with evidence. Research with consequence.'),
    'contact_email' => env('CONTACT_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS')),
    'features' => [
        'comments' => env('COMMENTS_ENABLED', true),
        'author_registration' => env('AUTHOR_REGISTRATION_ENABLED', true),
        'reviewer_workflow' => env('REVIEWER_WORKFLOW_ENABLED', true),
        'pdf_downloads' => env('PDF_DOWNLOADS_ENABLED', true),
        'newsletter' => env('NEWSLETTER_ENABLED', true),
    ],
    'uploads' => [
        'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'public')),
        'max_kilobytes' => (int) env('UPLOAD_MAX_KILOBYTES', 20480),
        'image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'document_mimes' => ['pdf', 'doc', 'docx'],
    ],
    'integrations' => [
        'analytics_id' => env('GOOGLE_ANALYTICS_ID'),
        'search_console_verification' => env('GOOGLE_SEARCH_CONSOLE_VERIFICATION'),
        'recaptcha_site_key' => env('RECAPTCHA_SITE_KEY'),
        'recaptcha_secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'turnstile_site_key' => env('CLOUDFLARE_TURNSTILE_SITE_KEY'),
        'turnstile_secret_key' => env('CLOUDFLARE_TURNSTILE_SECRET_KEY'),
    ],
    'rate_limits' => [
        'login' => (int) env('LOGIN_RATE_LIMIT', 5),
        'contact' => (int) env('CONTACT_RATE_LIMIT', 5),
    ],
    'seeding' => [
        'demo_content' => env('SEED_DEMO_CONTENT', false),
    ],
];
