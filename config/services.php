<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'newsletter' => [
        'provider' => env('NEWSLETTER_PROVIDER', 'database'),
        'key' => env('NEWSLETTER_API_KEY'),
    ],

    // The selected mailer must be configured for the journal's verified
    // Microsoft/Azure communication service. Secrets remain in environment
    // configuration and are intentionally never persisted or exposed to UI.
    'microsoft_email' => [
        'enabled' => env('MICROSOFT_EMAIL_ENABLED', false),
        'mailer' => env('MICROSOFT_EMAIL_MAILER', 'smtp'),
        'sender' => env('MICROSOFT_EMAIL_SENDER', env('MAIL_FROM_ADDRESS')),
        'sender_name' => env('MICROSOFT_EMAIL_SENDER_NAME', env('MAIL_FROM_NAME')),
        'reply_to' => env('MICROSOFT_EMAIL_REPLY_TO'),
        'endpoint' => env('MICROSOFT_EMAIL_ENDPOINT'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
