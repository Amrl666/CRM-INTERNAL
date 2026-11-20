<?php

return [
    'pdf_renderer' => env('PROPOSAL_PDF_RENDERER', 'playwright'), // playwright | fake

    'storage_disk' => env('PROPOSAL_STORAGE_DISK', 'local'),

    'document_path' => env('PROPOSAL_DOCUMENT_PATH', 'proposals'),

    'defaults' => [
        'timezone' => env('PROPOSAL_TIMEZONE', 'Asia/Jakarta'),
        'business_days' => [1, 2, 3, 4, 5],
        'business_start' => '09:00',
        'business_end' => '17:00',
        'rate_limit_per_hour' => (int) env('PROPOSAL_RATE_LIMIT_PER_HOUR', 10),
        'min_spacing_seconds' => (int) env('PROPOSAL_MIN_SPACING_SECONDS', 120),
    ],

    'whatsapp' => [
        // Default false. Must be explicitly enabled by an administrator after integration testing.
        'proposal_campaign_enabled' => (bool) env('WHATSAPP_PROPOSAL_CAMPAIGN_ENABLED', false),
        // Do not guess an Evolution endpoint. The administrator must enter the endpoint
        // verified against the pinned Evolution image version.
        'document_endpoint' => env('EVOLUTION_DOCUMENT_ENDPOINT'),
        'document_payload_mode' => env('EVOLUTION_DOCUMENT_PAYLOAD_MODE', 'sendMedia'),
    ],

    'gmail' => [
        'from_name' => env('PROPOSAL_GMAIL_FROM_NAME', env('PROPOSAL_FROM_NAME', 'Enigma Collective Studio')),
    ],

    'audience' => [
        'dynamic_enabled' => (bool) env('PROPOSAL_DYNAMIC_AUDIENCE_ENABLED', true),
        'multichannel_enabled' => (bool) env('PROPOSAL_MULTICHANNEL_ENABLED', true),
        'chunk_size' => (int) env('PROPOSAL_AUDIENCE_CHUNK_SIZE', 500),
        'preview_cache_seconds' => (int) env('PROPOSAL_AUDIENCE_PREVIEW_CACHE_SECONDS', 60),
    ],
];
