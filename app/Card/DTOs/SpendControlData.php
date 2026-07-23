<?php

declare(strict_types=1);

namespace App\Card\DTOs;

/** Provider-neutral spend controls (mirrors the Card control columns). */
final readonly class SpendControlData
{
    /**
     * @param  list<string>  $allowedCountries  ISO-3166-1 alpha-2
     * @param  list<string>  $blockedMccs  4-digit MCCs
     */
    public function __construct(
        public ?string $dailyLimitMinor = null,   // settlement-currency minor units
        public ?string $perTxLimitMinor = null,
        public string $currency = 'USD',
        public bool $onlineEnabled = true,
        public bool $atmEnabled = true,
        public bool $contactlessEnabled = true,
        public array $allowedCountries = [],
        public array $blockedMccs = [],
    ) {}
}
