<?php

declare(strict_types=1);

/*
 * SEO defaults for the public surface. The SEO system (App\Support\Seo\*, the
 * <x-seo.meta> component, sitemap + robots controllers) reads from here so every
 * public page shares one source of truth. Isolated from business logic.
 */
return [
    // Brand / publisher identity.
    'site_name' => env('APP_NAME', 'PaishaPay'),
    'tagline' => 'Borderless payments powered by digital assets',
    'default_title' => 'PaishaPay — Wallet, Cards, Exchange & P2P',
    'default_description' => 'PaishaPay is a multi-chain wallet for Bangladesh: hold USD and crypto, '
        .'issue virtual cards, exchange currencies, trade P2P and accept merchant payments — securely, in one app.',
    'keywords' => ['PaishaPay', 'crypto wallet', 'virtual card', 'USDT', 'P2P exchange', 'Bangladesh', 'stablecoin', 'merchant payments'],

    // Default social share image (1200×630 recommended). Served from /public.
    'default_image' => 'images/og-default.png',

    'locale' => 'en_US',
    'theme_color' => '#2053DD',      // matches the brand logo mark
    'twitter_handle' => env('SEO_TWITTER_HANDLE', '@paishapay'),

    // schema.org Organization — used for the sitewide Organization + WebSite JSON-LD.
    'organization' => [
        'name' => 'PaishaPay',
        'legal_name' => 'PaishaPay',
        'logo' => 'images/logo.svg',
        'email' => 'support@poisapay.com',
        // Public social profiles (schema.org sameAs). Fill with real URLs when live.
        'same_as' => array_values(array_filter(explode(',', (string) env('SEO_SAME_AS', '')))),
    ],

    // Path prefixes that must never be indexed (private/auth/app/admin surfaces).
    // Used by robots.txt and to auto-noindex matching routes.
    'noindex_prefixes' => [
        'dashboard', 'wallet', 'send', 'deposit', 'deposits', 'withdraw', 'withdrawals',
        'transfers', 'exchange', 'swap', 'cards', 'card', 'purchases', 'rewards', 'referrals',
        'settings', 'profile', 'kyc', 'notifications', 'security',
        'login', 'register', 'password', 'forgot-password', 'reset-password', 'verify-email', 'two-factor',
        'p2p', 'shop', 'checkout', 'admin', 'api', 'livewire', 'broadcasting', 'horizon',
    ],
];
