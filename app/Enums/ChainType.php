<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/**
 * Supported settlement chains (TDD §4.1). Tron-first per decision D7.
 */
enum ChainType: string
{
    use HasMeta;

    case Ethereum = 'ethereum';
    case Bsc = 'bsc';
    case Tron = 'tron';

    public function label(): string
    {
        return match ($this) {
            self::Ethereum => 'Ethereum',
            self::Bsc => 'BNB Smart Chain',
            self::Tron => 'Tron',
        };
    }

    public function coinType(): int
    {
        return match ($this) {
            self::Ethereum, self::Bsc => 60,
            self::Tron => 195,
        };
    }

    /** BIP44 derivation path template; {i} is the address index. */
    public function derivationPath(): string
    {
        return match ($this) {
            self::Ethereum => "m/44'/60'/0'/0/{i}",
            self::Bsc => "m/44'/60'/1'/0/{i}",
            self::Tron => "m/44'/195'/0'/0/{i}",
        };
    }

    public function isEvm(): bool
    {
        return $this === self::Ethereum || $this === self::Bsc;
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
            self::Tron => 19,
        };
    }

    public function nativeSymbol(): string
    {
        return match ($this) {
            self::Ethereum => 'ETH',
            self::Bsc => 'BNB',
            self::Tron => 'TRX',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ethereum => 'indigo',
            self::Bsc => 'warning',
            self::Tron => 'danger',
        };
    }
}
