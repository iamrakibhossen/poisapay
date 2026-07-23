<?php

declare(strict_types=1);

/*
 * P2P USDT marketplace configuration. Values here are structural defaults;
 * operators can override the tunable ones live via the settings engine
 * (getSetting('p2p_*')). Gated by the `p2p_enabled` feature flag (default off).
 */
return [
    // Platform taker fee (basis points) taken from the escrowed crypto on release.
    'taker_fee_bps' => (int) env('P2P_TAKER_FEE_BPS', 0),

    // Default payment window for new ads/orders, in minutes.
    'order_expiry_minutes' => (int) env('P2P_ORDER_EXPIRY_MINUTES', 15),

    // Whether both parties must be fully KYC-verified to trade (else Basic tier).
    'require_full_kyc' => (bool) env('P2P_REQUIRE_FULL_KYC', false),

    // Indicative fiat trade bounds (advisory; per-ad limits still apply).
    'min_trade_fiat' => env('P2P_MIN_TRADE_FIAT', '100'),
    'max_trade_fiat' => env('P2P_MAX_TRADE_FIAT', '500000'),
];
