<?php

declare(strict_types=1);

// Provider-agnostic card issuing. Controllers/ledger only ever talk to CardService;
// only app/Services/Card reads these credentials. Provider is chosen per card via
// the card_providers.driver column; default_provider is the fallback driver.
return [

    'default_provider' => env('CARD_PROVIDER', 'mock'),

    'providers' => [

        'mock' => [
            'driver' => \App\Card\Providers\Mock\MockCardProvider::class,
            'label' => 'Mock (Simulated)',
            'webhook_secret' => env('CARD_MOCK_WEBHOOK_SECRET', 'mock-webhook-secret'),
        ],

        // Marqeta Core API — Gateway JIT funding (our ledger decides each auth).
        'marqeta' => [
            'driver' => \App\Card\Providers\Marqeta\MarqetaProvider::class,
            'label' => 'Marqeta',
            'api_url' => env('CARD_MARQETA_URL', 'https://sandbox-api.marqeta.com/v3'),
            'application_token' => env('CARD_MARQETA_APP_TOKEN', 'd6b75be3-8c1c-41e6-8a2d-0584be8893b9'),
            'admin_token' => env('CARD_MARQETA_ADMIN_TOKEN', 'd332c566-5c39-4781-b047-f2cdcc288bf3'),
            'card_product_token' => env('CARD_MARQETA_CARD_PRODUCT_TOKEN', 'a1354e24-31aa-4d28-a0b5-d7be4c4432d4'),
            'network' => env('CARD_MARQETA_NETWORK', 'visa'),
            'webhook_secret' => env('CARD_MARQETA_WEBHOOK_SECRET', 'poisapay'),
            // Basic-auth creds Marqeta uses when calling our JIT/webhook endpoints.
            'inbound_username' => env('CARD_MARQETA_INBOUND_USER', 'poisapay'),
            'inbound_password' => env('CARD_MARQETA_INBOUND_PASS', 'a1354e24-31aa-4d28-a0b5-d7be4c4432d4A'),
        ],

        // Future adapters register here identically (stripe, lithic, highnote, …).
    ],

    'http' => [
        'timeout' => (int) env('CARD_HTTP_TIMEOUT', 15),
        'retry_attempts' => (int) env('CARD_HTTP_RETRY_ATTEMPTS', 2),
        'retry_sleep_ms' => (int) env('CARD_HTTP_RETRY_SLEEP_MS', 200),
    ],

    'inbound' => [
        'tolerate_seconds' => (int) env('CARD_WEBHOOK_TOLERANCE', 300),
        'jit_timeout_ms' => (int) env('CARD_JIT_TIMEOUT_MS', 1500),
    ],
];