<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Compliance\AccountGuard;
use App\Enums\P2pPriceType;
use App\Models\P2pAd;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Update an existing P2P ad. Side and asset are immutable (changing them on a
 * live ad with open orders is unsafe). `total_amount` may be raised or lowered,
 * but never below the amount already escrowed in open orders — `available_amount`
 * is recomputed as (new total − locked) under a row lock so a concurrent order
 * opening can't be double-spent.
 *
 * Expected $input keys: price_type, fixed_price|margin_bps, min_order, max_order,
 * total_amount (crypto base units), payment_window_min, terms?, decimals?, symbol?,
 * payment_method_ids[].
 */
class UpdateAdAction
{
    public function execute(User $user, P2pAd $ad, array $input): P2pAd
    {
        if (! feature('p2p_enabled', false)) {
            throw new RuntimeException('P2P marketplace is not enabled.');
        }

        AccountGuard::assertActive($user);

        if ($ad->user_id !== $user->getKey()) {
            throw new RuntimeException('You can only edit your own ads.');
        }

        $priceType = P2pPriceType::from($input['price_type']);
        if ($priceType === P2pPriceType::Fixed && empty($input['fixed_price'])) {
            throw new RuntimeException('A fixed-price ad needs a price.');
        }

        $decimals = (int) ($input['decimals'] ?? 6);
        $symbol = (string) ($input['symbol'] ?? 'USDT');

        return DB::transaction(function () use ($user, $ad, $input, $priceType, $decimals, $symbol): P2pAd {
            // Re-read under a row lock so `locked` is consistent with any order
            // opening concurrently against this ad.
            $ad = P2pAd::whereKey($ad->getKey())->lockForUpdate()->firstOrFail();

            $locked = Money::ofBase($ad->total_amount, $decimals, $symbol)
                ->minus(Money::ofBase($ad->available_amount, $decimals, $symbol));

            $newTotal = Money::ofBase((string) $input['total_amount'], $decimals, $symbol);

            if ($newTotal->isLessThan($locked)) {
                throw new RuntimeException('Total is below the '.$locked->format().' currently locked in open orders.');
            }

            $ad->update([
                'price_type' => $priceType,
                'fixed_price' => $input['fixed_price'] ?? null,
                'margin_bps' => $input['margin_bps'] ?? null,
                'min_order' => $input['min_order'],
                'max_order' => $input['max_order'],
                'total_amount' => $newTotal->baseString(),
                'available_amount' => $newTotal->minus($locked)->baseString(),
                'payment_window_min' => $input['payment_window_min'],
                'terms' => $input['terms'] ?? null,
            ]);

            if (isset($input['payment_method_ids'])) {
                $ad->paymentMethods()->sync($input['payment_method_ids']);
            }

            ActivityLogger::log('p2p.ad.updated', $ad, ['total' => $newTotal->baseString()], actor: $user);

            return $ad;
        });
    }
}
