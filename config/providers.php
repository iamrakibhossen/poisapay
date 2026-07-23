<?php

declare(strict_types=1);

use App\Domain\Chain\Evm\FakeBlockchainProvider;
use App\Domain\Chain\Evm\HttpBlockchainProvider;
use App\Domain\Compliance\Screening\StubScreeningProvider;
use App\Domain\Compliance\TravelRule\StubTravelRuleProvider;
use App\Domain\Exchange\StubRateProvider;
use App\Domain\Kyc\Providers\ManualKycProvider;
use App\Domain\Notification\Transports\FcmPushTransport;
use App\Domain\Notification\Transports\LogPushTransport;
use App\Domain\Notification\Transports\LogSmsTransport;
use App\Domain\Notification\Transports\TelegramTransport;
use App\Domain\Notification\Transports\TwilioSmsTransport;
use App\Domain\Notification\Transports\WhatsappTransport;
use App\Domain\Ramp\Payout\StubPayoutProcessor;
use App\Domain\Security\Geo\StubGeoLocator;
use App\Domain\Security\Reputation\StubIpReputationProvider;

/*
 * Provider adapter registry. Every external integration resolves through a driver
 * key, so swapping a stub for a real vendor is one line here + an env var — no
 * call-site changes. Defaults are the built-in stubs so the platform runs offline.
 */
return [
    'rates' => [
        // Live CoinGecko feed by default (degrades to the deterministic stub on any
        // outage); the test suite pins RATES_DRIVER=stub for reproducible values.
        'driver' => env('RATES_DRIVER', 'coingecko'),
        'cache_ttl' => (int) env('RATES_CACHE_TTL', 60), // seconds; 0 disables the caching decorator
        'drivers' => [
            'stub' => StubRateProvider::class,
            'coingecko' => \App\Domain\Exchange\CoinGeckoRateProvider::class,
        ],
    ],

    'screening' => [
        'driver' => env('SCREENING_DRIVER', 'stub'),
        'drivers' => [
            'stub' => StubScreeningProvider::class,
            // 'complyadvantage' => \App\Domain\Compliance\Screening\ComplyAdvantageProvider::class,
        ],
    ],

    'kyc' => [
        'driver' => env('KYC_DRIVER', 'manual'),
        'drivers' => [
            'manual' => ManualKycProvider::class,
            // 'sumsub' => \App\Domain\Kyc\Providers\SumsubProvider::class,
        ],
    ],

    'payout' => [
        'driver' => env('PAYOUT_DRIVER', 'stub'),
        'webhook_secret' => env('PAYOUT_WEBHOOK_SECRET'),
        'drivers' => [
            'stub' => StubPayoutProcessor::class,
            // 'wise' => \App\Domain\Ramp\Payout\WisePayoutProcessor::class,
        ],
    ],

    'ip_reputation' => [
        'driver' => env('IP_REPUTATION_DRIVER', 'stub'),
        'risk_threshold' => (int) env('IP_REPUTATION_THRESHOLD', 70),
        'drivers' => [
            'stub' => StubIpReputationProvider::class,
            // 'ipqualityscore' => \App\Domain\Security\Reputation\IpQualityScoreProvider::class,
        ],
    ],

    'geo' => [
        'driver' => env('GEO_DRIVER', 'stub'),
        'drivers' => [
            'stub' => StubGeoLocator::class,
            // 'maxmind' => \App\Domain\Security\Geo\MaxMindGeoLocator::class,
        ],
    ],

    'travel_rule' => [
        'driver' => env('TRAVEL_RULE_DRIVER', 'stub'),
        'drivers' => [
            'stub' => StubTravelRuleProvider::class,
            // 'notabene' => \App\Domain\Compliance\TravelRule\NotabeneProvider::class,
        ],
    ],

    // EVM JSON-RPC. The vendor is chosen by the rpc_endpoints URLs (Infura/Alchemy/
    // QuickNode/self-hosted/Anvil); 'fake' is the in-memory driver for tests.
    'blockchain' => [
        'driver' => env('BLOCKCHAIN_DRIVER', 'http'),
        'drivers' => [
            'http' => HttpBlockchainProvider::class,
            'fake' => FakeBlockchainProvider::class,
        ],
    ],

    'notifications' => [
        'sms' => env('SMS_DRIVER', 'log'),
        'push' => env('PUSH_DRIVER', 'log'),
        // WhatsApp/Telegram default to their real drivers, which no-op until the
        // vendor + per-user destination (phone / chat id) are configured.
        'whatsapp' => env('WHATSAPP_DRIVER', 'twilio'),
        'telegram' => env('TELEGRAM_DRIVER', 'telegram'),
        'drivers' => [
            'sms' => [
                'log' => LogSmsTransport::class,
                'twilio' => TwilioSmsTransport::class,
            ],
            'push' => [
                'log' => LogPushTransport::class,
                'fcm' => FcmPushTransport::class,
            ],
            'whatsapp' => [
                'twilio' => WhatsappTransport::class,
            ],
            'telegram' => [
                'telegram' => TelegramTransport::class,
            ],
        ],
    ],
];
