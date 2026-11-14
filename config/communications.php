<?php

return [

    'email' => [
        'default' => env('EMAIL_PROVIDER', 'gmail'),
        'gmail' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
            'scopes' => ['https://www.googleapis.com/auth/gmail.compose'],
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
];
