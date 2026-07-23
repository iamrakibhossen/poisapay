<?php

declare(strict_types=1);

use App\Card\Providers\Marqeta\MarqetaProvider;
use App\Card\Providers\Mock\MockCardProvider;
use App\Card\Providers\Stripe\StripeProvider;

// Provider-agnostic card issuing. Controllers/ledger only ever talk to CardService;
// only app/Services/Card reads these credentials. Provider is chosen per card via
// the card_providers.driver column; default_provider is the fallback driver.
return [

    'default_provider' => env('CARD_PROVIDER', 'mock'),

    'providers' => [

        'mock' => [
            'driver' => MockCardProvider::class,
            'label' => 'Mock (Simulated)',
            'webhook_secret' => env('CARD_MOCK_WEBHOOK_SECRET', 'mock-webhook-secret'),
        ],

        // Marqeta Core API — Gateway JIT funding (our ledger decides each auth).
        'marqeta' => [
            'driver' => MarqetaProvider::class,
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

        // Stripe Issuing — same contract as Marqeta, chosen by driver key. Keys in .env.
        'stripe' => [
            'driver' => StripeProvider::class,
            'label' => 'Stripe',
            'secret_key' => env('CARD_STRIPE_SECRET_KEY'),
            // Publishable key is safe to expose client-side; Stripe.js needs it to render
            // the PCI display Elements that show the PAN/CVV in the user's browser.
            'publishable_key' => env('CARD_STRIPE_PUBLISHABLE_KEY'),
            'webhook_secret' => env('CARD_STRIPE_WEBHOOK_SECRET'),
            'api_version' => env('CARD_STRIPE_API_VERSION') ?: null,
            // Ephemeral keys for card reveal must be minted with the version @stripe/stripe-js pins.
            'ephemeral_key_api_version' => env('CARD_STRIPE_EPHEMERAL_API_VERSION', '2020-03-02'),
            'network' => env('CARD_STRIPE_NETWORK', 'visa'),
            // Fallback cardholder billing address (Issuing requires one).
            'billing_address' => array_filter([
                'line1' => env('CARD_STRIPE_BILLING_LINE1', '123 Main Street'),
                'city' => env('CARD_STRIPE_BILLING_CITY', 'San Francisco'),
                'state' => env('CARD_STRIPE_BILLING_STATE', 'CA'),
                'postal_code' => env('CARD_STRIPE_BILLING_POSTAL', '94111'),
                'country' => env('CARD_STRIPE_BILLING_COUNTRY', 'US'),
            ]),
        ],

        // Future adapters register here identically (lithic, highnote, …).
    ],

    'http' => [
        'retry_attempts' => (int) env('CARD_HTTP_RETRY_ATTEMPTS', 2),
        'retry_sleep_ms' => (int) env('CARD_HTTP_RETRY_SLEEP_MS', 200),
    ],

    'inbound' => [
        'tolerate_seconds' => (int) env('CARD_WEBHOOK_TOLERANCE', 300),
        'jit_timeout_ms' => (int) env('CARD_JIT_TIMEOUT_MS', 1500),
    ],
];