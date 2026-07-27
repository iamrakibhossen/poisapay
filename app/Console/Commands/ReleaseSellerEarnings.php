<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ledger\LedgerService;
use App\Shop\Enums\OrderStatus;
use App\Shop\Models\Order;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Releases held seller earnings to spendable once the buyer's refund window has
 * passed. Each due order's net moves from the seller's locked balance to their
 * available balance (ledger unlock, idempotent per order). No-op while the
 * `sell_earnings_hold` flag is off — those orders were credited as spendable at
 * sale, so there is nothing to release.
 */
class ReleaseSellerEarnings extends Command
{
    protected $signature = 'paishapay:shop-release-earnings {--limit=500 : Max orders to process per run}';

    protected $description = 'Release held seller earnings to spendable after the refund window.';

    public function handle(LedgerService $ledger): int
    {
        if (! feature('shop_earnings_hold', false)) {
            $this->info('Earnings hold is disabled — nothing to release.');

            return self::SUCCESS;
        }

        $released = 0;

        Order::query()
            ->where('earnings_held', true)
            ->whereNull('earnings_released_at')
            ->where('refund_window_ends_at', '<=', now())
            ->whereNotIn('status', [
                OrderStatus::Refunded->value,
                OrderStatus::PartiallyRefunded->value,
                OrderStatus::Cancelled->value,
            ])
            ->with(['seller.user', 'asset'])
            ->limit((int) $this->option('limit'))
            ->get()
            ->each(function (Order $order) use ($ledger, &$released): void {
                DB::transaction(function () use ($order, $ledger, &$released): void {
                    // Re-check under lock — never release the same order twice.
                    $fresh = Order::whereKey($order->getKey())->lockForUpdate()->first();
                    if (! $fresh || ! $fresh->earnings_held || $fresh->earnings_released_at !== null) {
                        return;
                    }

                    $net = (int) $fresh->seller_net_amount;
                    $user = $order->seller?->user;

                    if ($user && $order->asset && $net > 0) {
                        $ledger->unlock(
                            $user,
                            (int) $fresh->asset_id,
                            Money::ofBase((string) $net, $order->asset->decimals, $order->asset->symbol),
                            'shop:release:'.$fresh->getKey(),
                            'shop.earnings_release',
                            ['order_id' => $fresh->getKey(), 'seller_id' => $fresh->seller_id],
                        );
                    }

                    $fresh->update(['earnings_released_at' => now()]);
                    $released++;
                });
            });

        $this->info("Released earnings for {$released} order(s).");

        return self::SUCCESS;
    }
}
