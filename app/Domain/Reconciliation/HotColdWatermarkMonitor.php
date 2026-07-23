<?php

declare(strict_types=1);

namespace App\Domain\Reconciliation;

use App\Domain\Ledger\AccountResolver;
use App\Enums\LedgerAccountType;
use App\Models\Asset;

/**
 * Hot-wallet watermark monitor. For each active asset it reads the treasury:hot ledger
 * balance and flags when it is above the high-watermark (sweep excess to cold) or below
 * the low-watermark (refill from cold). It is READ-ONLY and alert-only: it never moves
 * funds. Cold → hot cannot be automated (the cold key is offline by design), so a low
 * balance is surfaced for the operator's approved, offline-signed refill. High/low
 * thresholds come from settings per asset symbol; a threshold of 0 disables that check,
 * so the monitor is inert until an operator configures watermarks.
 */
class HotColdWatermarkMonitor
{
    public function __construct(private readonly AccountResolver $accounts) {}

    /**
     * @return list<array{asset: string, hot: string, high: string, low: string, state: string}>
     */
    public function evaluate(): array
    {
        $report = [];

        foreach (Asset::where('is_active', true)->whereNotNull('contract_address')->get() as $asset) {
            $high = (string) getSetting("custody.watermark.high.{$asset->symbol}", '0');
            $low = (string) getSetting("custody.watermark.low.{$asset->symbol}", '0');

            $hot = ltrim(
                $this->accounts->system(LedgerAccountType::TreasuryHot, $asset->id)->fresh('balance')->money()->baseString(),
                '-',
            );

            $state = 'ok';
            if (bccomp($high, '0') > 0 && bccomp($hot, $high) > 0) {
                $state = 'over';
                notifyAdmins(
                    'Hot wallet above high-watermark',
                    "{$asset->symbol} treasury:hot is {$hot} (high-watermark {$high}). Sweep the excess to cold storage.",
                    null,
                    'security',
                );
            } elseif (bccomp($low, '0') > 0 && bccomp($hot, $low) < 0) {
                $state = 'under';
                notifyAdmins(
                    'Hot wallet below low-watermark',
                    "{$asset->symbol} treasury:hot is {$hot} (low-watermark {$low}). Refill from cold storage (offline-signed).",
                    null,
                    'security',
                );
            }

            $report[] = ['asset' => (string) $asset->symbol, 'hot' => $hot, 'high' => $high, 'low' => $low, 'state' => $state];
        }

        return $report;
    }
}
