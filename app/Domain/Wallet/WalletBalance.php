<?php

declare(strict_types=1);

namespace App\Domain\Wallet;

use App\Models\Asset;
use App\Support\Money;

/** Immutable snapshot of one asset's balance for a user. */
final readonly class WalletBalance
{
    public function __construct(
        public Asset $asset,
        public Money $available,
        public Money $locked,
    ) {}

    public function total(): Money
    {
        return $this->available->plus($this->locked);
    }

    public function toArray(): array
    {
        return [
            'asset' => $this->asset->symbol,
            'asset_name' => $this->asset->name,
            'decimals' => $this->asset->decimals,
            'available' => $this->available->toDecimal(),
            'locked' => $this->locked->toDecimal(),
            'total' => $this->total()->toDecimal(),
        ];
    }
}
