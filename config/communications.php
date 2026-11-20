<?php

return [

    'email' => [
        'default' => env('EMAIL_PROVIDER', 'gmail'),
        'gmail' => [
            'primary_email' => env('GMAIL_PRIMARY_EMAIL', 'enigmativebusiness@gmail.com'),
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
            'scopes' => [
                'https://www.googleapis.com/auth/gmail.compose',
                'https://www.googleapis.com/auth/gmail.send',
                'https://www.googleapis.com/auth/gmail.readonly',
                'openid',
                'email',
                'profile',
            ],
            'sync' => [
                'enabled' => (bool) env('GMAIL_SYNC_ENABLED', false),
                'interval_minutes' => (int) env('GMAIL_SYNC_INTERVAL_MINUTES', 15),
                // Each job page refreshes the running marker. A marker with
                // no heartbeat for the stale window may be reclaimed by a
                // new dispatch. Keep the lock TTL >= the stale window.
                'lock_ttl_minutes' => (int) env('GMAIL_SYNC_LOCK_TTL_MINUTES', 60),
                'stale_after_minutes' => (int) env('GMAIL_SYNC_STALE_AFTER_MINUTES', 30),
                'failure_ttl_days' => (int) env('GMAIL_SYNC_FAILURE_TTL_DAYS', 7),
                'cache_store' => env('GMAIL_SYNC_CACHE_STORE') ?: null,
            ],
        ],
    ],

    'whatsapp' => [
        'default' => env('WHATSAPP_PROVIDER', 'evolution'),
        'outbound_mode' => env('WHATSAPP_OUTBOUND_MODE', 'manual'),
        'evolution' => [
            'url' => env('EVOLUTION_API_URL'),
            'api_key' => env('EVOLUTION_API_KEY'),
            'instance' => env('EVOLUTION_INSTANCE'),
            'webhook_secret' => env('EVOLUTION_WEBHOOK_SECRET'),
        ],
    ],

    'email_banner' => [
        // Absolute path to the JPEG appended to every Gmail draft.
        // Leave empty to use public/images/email-footer-banner.jpg.
        'path' => env('EMAIL_BANNER_PATH'),
        'cid' => 'email-footer-banner',
    ],
];
