<?php

declare(strict_types=1);

/*
 * Shop (commerce) module configuration. Structural defaults; operator-tunable
 * values still flow through the settings engine where noted. The module itself
 * is gated by the `shop_enabled` feature flag (settings).
 */
return [
    // ── Custom domains (one per sales page) ─────────────────────────────────
    // Merchants point their own domain at the platform; we verify ownership via
    // DNS, provision SSL, and route the domain straight to the sales page.
    // Whole feature gated by the `shop_custom_domains` flag (default off).
    'custom_domains' => [
        // CNAME-only: every custom domain points here with a CNAME record. A
        // subdomain (shop.brand.com) is recommended; apex/root domains work only
        // where the DNS provider supports CNAME-flattening / ALIAS.
        'cname_target' => env('SHOP_DOMAIN_CNAME_TARGET', 'connect.poisapay.com'),
        'dns_ttl' => (int) env('SHOP_DOMAIN_DNS_TTL', 3600),

        // Ownership challenge — a TXT record proves the merchant controls the DNS
        // zone (blocks pointing-at-us-without-owning and domain-takeover replays).
        'txt_name' => '_poisapay-challenge',
        'txt_prefix' => 'poisapay-domain-verification=',

        // Automatic verification retry (queued with backoff) until this ceiling.
        'verify_max_attempts' => (int) env('SHOP_DOMAIN_VERIFY_ATTEMPTS', 10),
        'verify_backoff' => (int) env('SHOP_DOMAIN_VERIFY_BACKOFF', 120),

        'ssl' => [
            // `simulated` marks certs active without a real CA (dev/test + until an
            // ACME/edge integration is wired). `acme` is the production driver.
            'driver' => env('SHOP_DOMAIN_SSL_DRIVER', 'simulated'),
            'max_attempts' => (int) env('SHOP_DOMAIN_SSL_ATTEMPTS', 5),
            'backoff' => (int) env('SHOP_DOMAIN_SSL_BACKOFF', 300),
        ],

        // Routing lookup cache (host → sales page). Invalidated on any domain write.
        'cache_ttl' => (int) env('SHOP_DOMAIN_CACHE_TTL', 3600),

        // Hosts owned by the platform: never connectable, and the domain router
        // passes them straight through to normal routing. Any subdomain of a
        // `platform_apexes` entry is also treated as platform (e.g. shop.poisapay.com).
        'platform_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'SHOP_PLATFORM_HOSTS',
            'pay.rakibhossen.com,pstaging.rakibhossen.com',
        ))))),
        'platform_apexes' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'SHOP_PLATFORM_APEXES',
            'poisapay.com,poisahub.com',
        ))))),

        // Suffixes/hosts that can never be connected (RFC 2606/6761 + internal).
        'reserved_suffixes' => [
            '.local', '.localhost', '.test', '.example', '.invalid',
            '.internal', '.localdomain', '.arpa', '.onion',
        ],
        'reserved_hosts' => [
            'localhost', 'example.com', 'example.org', 'example.net',
        ],
    ],
];
