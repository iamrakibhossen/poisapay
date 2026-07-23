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

        // EVM chains (Wave 2). RPC URL falls back here when no rpc_endpoints row
        // exists; for production add Infura/Alchemy/QuickNode URLs to rpc_endpoints.
        // For local integration testing point *_RPC at Anvil (http://127.0.0.1:8545).
        // Stablecoin contracts are mainnet addresses; each becomes a seeded Asset the
        // multi-token watcher scans. Point *_RPC at your provider (Infura/Alchemy/
        // QuickNode) via rpc_endpoints in production, or Anvil for local testing.
        'ethereum' => [
            'rpc' => env('POISAPAY_ETHEREUM_RPC', 'http://127.0.0.1:8545'),
            'chain_id' => (int) env('POISAPAY_ETHEREUM_CHAIN_ID', 1),
            'usdt_contract' => env('POISAPAY_ETHEREUM_USDT', '0xdAC17F958D2ee523a2206206994597C13D831ec7'),
            'usdc_contract' => env('POISAPAY_ETHEREUM_USDC', '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48'),
            'confirmations' => (int) env('POISAPAY_ETHEREUM_CONFIRMATIONS', 12),
        ],
        'bsc' => [
            'rpc' => env('POISAPAY_BSC_RPC', 'http://127.0.0.1:8545'),
            'chain_id' => (int) env('POISAPAY_BSC_CHAIN_ID', 56),
            'usdt_contract' => env('POISAPAY_BSC_USDT', '0x55d398326f99059fF775485246999027B3197955'),
            'usdc_contract' => env('POISAPAY_BSC_USDC', '0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d'),
            'token_decimals' => (int) env('POISAPAY_BSC_TOKEN_DECIMALS', 18), // BSC USDT/USDC are 18-decimal on-chain
            'confirmations' => (int) env('POISAPAY_BSC_CONFIRMATIONS', 15),
        ],
        'polygon' => [
            'rpc' => env('POISAPAY_POLYGON_RPC', 'http://127.0.0.1:8545'),
            'chain_id' => (int) env('POISAPAY_POLYGON_CHAIN_ID', 137),
            'usdt_contract' => env('POISAPAY_POLYGON_USDT', '0xc2132D05D31c914a87C6611C10748AEb04B58e8F'),
            'usdc_contract' => env('POISAPAY_POLYGON_USDC', '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359'),
            'confirmations' => (int) env('POISAPAY_POLYGON_CONFIRMATIONS', 30),
        ],
        'arbitrum' => [
            'rpc' => env('POISAPAY_ARBITRUM_RPC', 'http://127.0.0.1:8545'),
            'chain_id' => (int) env('POISAPAY_ARBITRUM_CHAIN_ID', 42161),
            'usdt_contract' => env('POISAPAY_ARBITRUM_USDT', '0xFd086bC7CD5C481DCC9C85ebE478A1C0b69FCbb9'),
            'usdc_contract' => env('POISAPAY_ARBITRUM_USDC', '0xaf88d065e77c8cC2239327C5EDb3A432268e5831'),
            'confirmations' => (int) env('POISAPAY_ARBITRUM_CONFIRMATIONS', 20),
        ],
        'optimism' => [
            'rpc' => env('POISAPAY_OPTIMISM_RPC', 'http://127.0.0.1:8545'),
            'chain_id' => (int) env('POISAPAY_OPTIMISM_CHAIN_ID', 10),
            'usdt_contract' => env('POISAPAY_OPTIMISM_USDT', '0x94b008aA00579c1307B0EF2c499aD98a8ce58e58'),
            'usdc_contract' => env('POISAPAY_OPTIMISM_USDC', '0x0b2C639c533813f4Aa9D7837CAf62653d097Ff85'),
            'confirmations' => (int) env('POISAPAY_OPTIMISM_CONFIRMATIONS', 20),
        ],
        'base' => [
            'rpc' => env('POISAPAY_BASE_RPC', 'http://127.0.0.1:8545'),
            'chain_id' => (int) env('POISAPAY_BASE_CHAIN_ID', 8453),
            'usdt_contract' => env('POISAPAY_BASE_USDT', ''), // no canonical USDT on Base
            'usdc_contract' => env('POISAPAY_BASE_USDC', '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913'),
            'confirmations' => (int) env('POISAPAY_BASE_CONFIRMATIONS', 20),
        ],
        'avalanche' => [
            'rpc' => env('POISAPAY_AVALANCHE_RPC', 'http://127.0.0.1:8545'),
            'chain_id' => (int) env('POISAPAY_AVALANCHE_CHAIN_ID', 43114),
            'usdt_contract' => env('POISAPAY_AVALANCHE_USDT', '0x9702230A8Ea53601f5cD2dc00fDBc13d4dF4A8c7'),
            'usdc_contract' => env('POISAPAY_AVALANCHE_USDC', '0xB97EF9Ef8734C71904D8002F8b6Bc66Dd9c48a6E'),
            'confirmations' => (int) env('POISAPAY_AVALANCHE_CONFIRMATIONS', 15),
        ],
        // Block-explorer base URLs per chain (used to build txid / address links).
        'explorers' => [
            'ethereum' => env('POISAPAY_ETHEREUM_EXPLORER', 'https://etherscan.io'),
            'bsc' => env('POISAPAY_BSC_EXPLORER', 'https://bscscan.com'),
            'polygon' => env('POISAPAY_POLYGON_EXPLORER', 'https://polygonscan.com'),
            'arbitrum' => env('POISAPAY_ARBITRUM_EXPLORER', 'https://arbiscan.io'),
            'optimism' => env('POISAPAY_OPTIMISM_EXPLORER', 'https://optimistic.etherscan.io'),
            'base' => env('POISAPAY_BASE_EXPLORER', 'https://basescan.org'),
            'avalanche' => env('POISAPAY_AVALANCHE_EXPLORER', 'https://snowtrace.io'),
            'tron' => env('POISAPAY_TRON_EXPLORER', 'https://tronscan.org'),
        ],
        // Range of blocks scanned per watcher tick (bounds eth_getLogs window).
        'evm_scan_range' => (int) env('POISAPAY_EVM_SCAN_RANGE', 500),
        // Gas limit used for ERC-20 transfers when estimation is unavailable.
        'evm_transfer_gas' => (int) env('POISAPAY_EVM_TRANSFER_GAS', 90000),
    ],

    // Currency shown as the user's aggregate total (§F1.1).
    'base_currency' => env('POISAPAY_BASE_CURRENCY', 'USD'),

    // Default exchange spread applied to quotes, in basis points (§F2.1).
    'default_spread_bps' => (int) env('POISAPAY_DEFAULT_SPREAD_BPS', 75),

    // Optional explicit platform swap fee (bps), booked to fee:income on top of
    // the spread. 0 = spread-only (unchanged behaviour); admin overrides live.
    'default_fee_bps' => (int) env('POISAPAY_DEFAULT_FEE_BPS', 0),

    // Per-user swap guardrails (user-initiated Swap context only; ramp/card are
    // exempt). Defaults are permissive so nothing changes until an admin opts in:
    // min KYC tier to swap, and rolling-24h swap notional ceiling in whole USD
    // (0 = unlimited). Overridden live via Settings.
    'swap_min_kyc' => env('POISAPAY_SWAP_MIN_KYC', 'unverified'),
    'swap_daily_limit_usd' => (int) env('POISAPAY_SWAP_DAILY_LIMIT_USD', 0),

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

    // Security hardening (Wave 4). Per-module on/off flags are also editable live
    // via the settings engine (feature('security_*')); numeric tunables fall back
    // to these defaults but can be overridden via getSetting(). Enforcement gates
    // default to a safe, backward-compatible value.
    'security' => [
        'flags' => [
            'withdrawal_whitelist' => false, // enforce cash-out only to whitelisted addresses
            'address_cooldown' => true,      // new addresses sit in cooldown before use
            'suspicious_login' => true,      // detect + alert on anomalous logins
            'ip_reputation' => true,         // consult the IP reputation adapter
            'geo_risk' => true,              // country-risk + impossible-travel checks
            'velocity_limits' => true,       // daily withdrawal velocity caps
            'audit_hash_chain' => true,      // tamper-evident hash chaining of audit logs
            'travel_rule' => false,          // capture originator/beneficiary above threshold
        ],
        // Travel Rule threshold in the asset's display units (FATF R.16 ~ USD 1,000).
        'travel_rule_threshold' => (int) env('SEC_TRAVEL_RULE_THRESHOLD', 1000),
        // Hours a newly added withdrawal address is unusable (24–48h recommended).
        'address_cooldown_hours' => (int) env('SEC_ADDRESS_COOLDOWN_HOURS', 24),
        // Max completed+pending withdrawals per rolling 24h before a hold is forced.
        'daily_withdrawal_count' => (int) env('SEC_DAILY_WD_COUNT', 10),
        // Implausible travel speed between two logins (km/h) → impossible-travel flag.
        'impossible_travel_kmh' => (int) env('SEC_IMPOSSIBLE_TRAVEL_KMH', 900),
        // ISO-3166 alpha-2 high-risk jurisdictions (FATF-style; operator-tunable).
        'high_risk_countries' => ['KP', 'IR', 'SY', 'CU'],
    ],

    // Rewards policy (§F5).
    'rewards' => [
        'welcome_bonus_bdt' => 5000,   // 50.00 BDT in paisa
        'referrer_bonus_bdt' => 20000, // 200.00 BDT
        'referee_bonus_bdt' => 10000,  // 100.00 BDT
    ],
];