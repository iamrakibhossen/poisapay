<?php

declare(strict_types=1);

namespace App\Domain\Merchant;

use App\Models\Asset;
use App\Models\MerchantInvoice;
use App\Models\User;
use App\Support\Money;

/**
 * Create (or return the existing) merchant invoice (TDD §8.2). Idempotent by
 * (merchant, reference) so a repeated create for the same order is a no-op.
 */
class CreateInvoiceAction
{
    public function execute(User $merchant, Asset $asset, Money $amount, string $reference, ?string $memo = null, ?int $ttlMinutes = 60): MerchantInvoice
    {
        return MerchantInvoice::firstOrCreate(
            ['merchant_id' => $merchant->id, 'reference' => $reference],
            [
                'asset_id' => $asset->id,
                'amount' => $amount->baseString(),
                'memo' => $memo,
                'status' => 'pending',
                'expires_at' => $ttlMinutes ? now()->addMinutes($ttlMinutes) : null,
            ],
        );
    }
}
