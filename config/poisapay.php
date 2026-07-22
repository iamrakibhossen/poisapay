<?php

declare(strict_types=1);

return [
    // Custody is simulated until a real HD deriver + chain watcher/signer are wired.
    // While true, crypto deposit addresses are demo-only — the platform does NOT
    // hold their keys, so a "do not send real funds" notice is shown. Set false
    // (POISAPAY_CUSTODY_LIVE=true) once real on-chain custody is integrated.
    'custody_simulated' => ! filter_var(env('POISAPAY_CUSTODY_LIVE', false), FILTER_VALIDATE_BOOL),

    // Real custody configuration (used only when custody_simulated is false).
    'custody' => [
        // BIP32 master seed for the signing zone. Store as a Laravel-encrypted
        // string (encrypt at rest) or a hex seed for local dev. NEVER commit a
        // real seed. In production this should be replaced by a KMS/HSM-backed
        // SignerKeyProvider (bind a different implementation) and left unset here.
        'seed' => env('POISAPAY_CUSTODY_SEED'),

        'tron' => [
            // Testnet defaults (Nile). Override for mainnet.
            'rpc' => env('POISAPAY_TRON_RPC', 'https://nile.trongrid.io'),
            'api_key' => env('POISAPAY_TRONGRID_KEY'),
            // USDT (TRC20) contract address on the configured network.
            'usdt_contract' => env('POISAPAY_TRON_USDT', 'TXLAQ63Xg1NAzckPwKHvzw7CSEmLMEqcdj'),
            // How deep a block must be before a deposit is credited / withdrawal settled.
            'confirmations' => (int) env('POISAPAY_TRON_CONFIRMATIONS', 19),
        ],
    ],

    // Currency shown as the user's aggregate total (§F1.1).
    'base_currency' => env('POISAPAY_BASE_CURRENCY', 'BDT'),

    // Default exchange spread applied to quotes, in basis points (§F2.1).
    'default_spread_bps' => (int) env('POISAPAY_DEFAULT_SPREAD_BPS', 75),

    // Platform percentage fees (the admin's cut) booked to fee:income. These are
    // the fallback defaults; the admin overrides them live via Settings. Deposits
    // credit the user net of the fee; withdrawals add it on top of the rail fee.
    'deposit_fee_percent' => env('POISAPAY_DEPOSIT_FEE_PERCENT', '1'),
    'withdrawal_fee_percent' => env('POISAPAY_WITHDRAWAL_FEE_PERCENT', '1'),

    // Withdrawals at or below this amount (settlement minor units) may auto-approve (§10.3).
    'withdrawal_auto_approve_limit' => (int) env('POISAPAY_WITHDRAWAL_AUTO_APPROVE_LIMIT', 50000),

    // Card authorisation p99 latency budget in ms — hard NFR (§F7).
    'card_auth_p99_ms' => (int) env('POISAPAY_CARD_AUTH_P99_MS', 2000),

    // OTP policy.
    'otp' => [
        'ttl_seconds' => 300,
        'daily_cap' => 10,
        'length' => 6,
    ],

    // Rewards policy (§F5).
    'rewards' => [
        'welcome_bonus_bdt' => 5000,   // 50.00 BDT in paisa
        'referrer_bonus_bdt' => 20000, // 200.00 BDT
        'referee_bonus_bdt' => 10000,  // 100.00 BDT
    ],
];
