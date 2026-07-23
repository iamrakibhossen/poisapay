<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/**
 * Supported settlement chains (TDD §4.1). EVM chains share BIP44 coin type 60, so
 * a user's 0x deposit address is identical across every EVM network (RedotPay
 * model). Adding another EVM chain is just a case here + config + a seeded asset;
 * the watcher/signer/derivation are already generic.
 */
enum ChainType: string
{
    use HasMeta;

    case Ethereum = 'ethereum';
    case Bsc = 'bsc';
    case Polygon = 'polygon';
    case Arbitrum = 'arbitrum';
    case Optimism = 'optimism';
    case Base = 'base';
    case Avalanche = 'avalanche';
    case Tron = 'tron';

    /** @return array<int, self> */
    public static function evmChains(): array
    {
        return [self::Ethereum, self::Bsc, self::Polygon, self::Arbitrum, self::Optimism, self::Base, self::Avalanche];
    }

    public function label(): string
    {
        return match ($this) {
            self::Ethereum => 'Ethereum',
            self::Bsc => 'BNB Smart Chain',
            self::Polygon => 'Polygon',
            self::Arbitrum => 'Arbitrum One',
            self::Optimism => 'Optimism',
            self::Base => 'Base',
            self::Avalanche => 'Avalanche C-Chain',
            self::Tron => 'Tron',
        };
    }

    public function coinType(): int
    {
        return $this === self::Tron ? 195 : 60; // all EVM chains share coin type 60
    }

    /** BIP44 derivation path template; {i} is the address index. */
    public function derivationPath(): string
    {
        return $this === self::Tron ? "m/44'/195'/0'/0/{i}" : "m/44'/60'/0'/0/{i}";
    }

    public function isEvm(): bool
    {
        return in_array($this, self::evmChains(), true);
    }

    public function addressEncoding(): string
    {
        return $this->isEvm() ? 'eip55' : 'base58check';
    }

    /** Default confirmations before a deposit is credited (D6, per-asset overridable). */
    public function defaultConfirmations(): int
    {
        return match ($this) {
            self::Ethereum => 12,
            self::Bsc => 15,
            self::Polygon => 30,
            self::Arbitrum, self::Optimism, self::Base => 20,
            self::Avalanche => 15,
            self::Tron => 19,
        };
    }

    public function nativeSymbol(): string
    {
        return match ($this) {
            self::Ethereum, self::Arbitrum, self::Optimism, self::Base => 'ETH',
            self::Bsc => 'BNB',
            self::Polygon => 'POL',
            self::Avalanche => 'AVAX',
            self::Tron => 'TRX',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ethereum => 'indigo',
            self::Bsc => 'warning',
            self::Polygon => 'purple',
            self::Arbitrum => 'info',
            self::Optimism => 'danger',
            self::Base => 'primary',
            self::Avalanche => 'danger',
            self::Tron => 'danger',
        };
    }
}
