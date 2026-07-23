<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Compliance\AccountGuard;
use App\Enums\KycTier;
use App\Enums\P2pAdStatus;
use App\Enums\P2pAdType;
use App\Enums\P2pPriceType;
use App\Models\P2pAd;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Create a P2P ad. No funds are locked at ad time (Binance model — escrow
 * happens per order); `available_amount` seeds the advertised inventory.
 *
 * Expected $input keys: side, asset_id, fiat_currency, price_type, fixed_price|margin_bps,
 * min_order, max_order, total_amount (crypto base units), daily_limit?, payment_window_min?,
 * terms?, auto_reply?, countries?, payment_method_ids[] (uuid).
 */
class CreateAdAction
{
    public function execute(User $user, array $input): P2pAd
    {
        if (! feature('p2p_enabled', false)) {
            throw new RuntimeException('P2P marketplace is not enabled.');
        }

        AccountGuard::assertActive($user);

        $required = feature('p2p_require_full_kyc', false) ? KycTier::Full : KycTier::Basic;
        if (! $user->tier()->atLeast($required)) {
            throw new RuntimeException('Complete the required verification to post an ad.');
        }

        $priceType = P2pPriceType::from($input['price_type']);
        if ($priceType === P2pPriceType::Fixed && empty($input['fixed_price'])) {
            throw new RuntimeException('A fixed-price ad needs a price.');
        }

        $total = Money::ofBase((string) $input['total_amount'], (int) ($input['decimals'] ?? 6), (string) ($input['symbol'] ?? 'USDT'));

        return DB::transaction(function () use ($user, $input, $priceType, $total): P2pAd {
            $ad = P2pAd::create([
                'user_id' => $user->getKey(),
                'side' => P2pAdType::from($input['side']),
                'asset_id' => (int) $input['asset_id'],
                'fiat_currency' => $input['fiat_currency'] ?? 'BDT',
                'price_type' => $priceType,
                'fixed_price' => $input['fixed_price'] ?? null,
                'margin_bps' => $input['margin_bps'] ?? null,
                'min_order' => $input['min_order'],
                'max_order' => $input['max_order'],
                'available_amount' => $total->baseString(),
                'total_amount' => $total->baseString(),
                'daily_limit' => $input['daily_limit'] ?? null,
                'payment_window_min' => $input['payment_window_min'] ?? 15,
                'min_completion_bps' => $input['min_completion_bps'] ?? null,
                'auto_reply' => $input['auto_reply'] ?? null,
                'terms' => $input['terms'] ?? null,
                'countries' => $input['countries'] ?? null,
                'trade_hours' => $input['trade_hours'] ?? null,
                'status' => P2pAdStatus::from($input['status'] ?? P2pAdStatus::Active->value),
                'priority' => $input['priority'] ?? 0,
            ]);

            if (! empty($input['payment_method_ids'])) {
                $ad->paymentMethods()->sync($input['payment_method_ids']);
            }

            ActivityLogger::log('p2p.ad.created', $ad, ['side' => $ad->side->value], actor: $user);

            return $ad;
        });
    }
}
