<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ledger\LedgerService;
use App\Domain\Notification\NotificationService;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Seller;
use App\Support\Money;
use Illuminate\Console\Command;

/**
 * Alerts sellers whose ledger-available balance in their settlement asset has
 * fallen to/below the threshold — relevant because refunds can claw back from a
 * seller's available balance and push it negative. Idempotent per seller per day
 * (dedupe key includes the date), so it never spams on repeated runs.
 */
class ShopLowBalanceAlerts extends Command
{
    protected $signature = 'shop:low-balance-alerts';

    protected $description = 'Notify sellers whose available balance is at or below the alert threshold.';

    public function handle(NotificationService $notifications, LedgerService $ledger): int
    {
        $thresholdWhole = (int) getSetting('shop_low_balance_threshold', 0);
        $today = now()->toDateString();
        $sent = 0;

        Seller::query()
            ->where('status', SellerStatus::Approved)
            ->whereNotNull('settlement_asset_id')
            ->with(['user', 'settlementAsset'])
            ->chunkById(200, function ($sellers) use (&$sent, $notifications, $ledger, $thresholdWhole, $today) {
                foreach ($sellers as $seller) {
                    $user = $seller->user;
                    $asset = $seller->settlementAsset;
                    if ($user === null || $asset === null) {
                        continue;
                    }

                    $available = $ledger->availableBalance($user, (int) $asset->id);
                    $threshold = Money::ofDecimal((string) $thresholdWhole, $asset->decimals, $asset->symbol);
                    if ($available->base->isGreaterThan($threshold->base)) {
                        continue; // healthy
                    }

                    $notifications->send(
                        $user,
                        'shop.balance.low',
                        ['amount' => $available->format()],
                        null,
                        route('shop'),
                        'shop.balance.low:'.$seller->id.':'.$today,
                    );
                    $sent++;
                }
            });

        $this->info("Sent {$sent} low-balance alert(s).");

        return self::SUCCESS;
    }
}
