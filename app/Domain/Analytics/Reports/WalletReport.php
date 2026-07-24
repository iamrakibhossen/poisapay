<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\LedgerAggregates;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Enums\LedgerAccountType as T;

/**
 * Wallet & balance-sheet analytics. Every figure is recomputed from the ledger,
 * so the solvency check (treasury controlled vs user liabilities) is authoritative
 * — a negative surplus raises a critical alert.
 */
class WalletReport extends Report
{
    private const USER_FUNDS = [T::UserAvailable, T::UserLocked, T::UserCardHold, T::UserP2pEscrow, T::UserCollateralLocked];

    private const TREASURY = [T::TreasuryHot, T::TreasuryCold, T::TreasuryPending];

    public function __construct(private readonly LedgerAggregates $ledger) {}

    public function key(): string
    {
        return 'wallet';
    }

    public function title(): string
    {
        return 'Wallet Analytics';
    }

    public function build(Period $period): array
    {
        $e = $this->envelope();

        $byAsset = collect($this->ledger->usdByAsset(self::USER_FUNDS))->keyBy('symbol');
        $userUsd = (float) $this->ledger->usdTotal(self::USER_FUNDS);
        $treasuryUsd = (float) $this->ledger->usdTotal(self::TREASURY);
        $difference = round($treasuryUsd - $userUsd, 2);

        $e['kpis'] = [
            $this->kpi('Total Wallet Balance', $this->usdFmt($userUsd), ['accent' => 'brand', 'icon' => 'wallet']),
            $this->kpi('Total USD Balance', $this->usdFmt($byAsset->get('USD')['usd'] ?? 0), ['accent' => 'emerald']),
            $this->kpi('Total USDT Balance', $this->usdFmt($byAsset->get('USDT')['usd'] ?? 0), ['accent' => 'emerald']),
            $this->kpi('Locked / Reserved', $this->usdFmt($this->ledger->usdTotal([T::UserLocked, T::UserCardHold, T::UserP2pEscrow, T::UserCollateralLocked])), ['accent' => 'amber']),
            $this->kpi('Treasury Balance', $this->usdFmt($treasuryUsd), ['accent' => 'brand', 'icon' => 'building-library']),
            $this->kpi('Hot Wallet', $this->usdFmt($this->ledger->usdTotal([T::TreasuryHot])), ['accent' => 'rose']),
            $this->kpi('Cold Wallet', $this->usdFmt($this->ledger->usdTotal([T::TreasuryCold])), ['accent' => 'sky']),
            $this->kpi('Ledger Difference', $this->usdFmt($difference), [
                'accent' => $difference >= 0 ? 'emerald' : 'rose',
                'hint' => $difference >= 0 ? 'Treasury covers liabilities' : 'Deficit — investigate',
            ]),
        ];

        $funds = collect($this->ledger->usdByAsset(self::USER_FUNDS));
        if ($funds->isNotEmpty()) {
            $e['charts'][] = $this->chart('wallet-by-asset', 'User balances by asset', 'doughnut',
                $funds->pluck('symbol')->all(),
                [$this->dataset('USD value', $funds->pluck('usd')->all())],
                ['span' => 'half']);
        }

        $e['charts'][] = $this->chart('treasury-split', 'Treasury: hot / cold / pending', 'bar',
            ['Hot', 'Cold', 'Pending'],
            [$this->dataset('USD', [
                (float) $this->ledger->usdTotal([T::TreasuryHot]),
                (float) $this->ledger->usdTotal([T::TreasuryCold]),
                (float) $this->ledger->usdTotal([T::TreasuryPending]),
            ], '#0ea5e9')],
            ['span' => 'half']);

        if ($difference < 0) {
            $e['alerts'][] = ['level' => 'critical', 'title' => 'Ledger / solvency mismatch',
                'message' => 'Treasury balances are '.$this->usdFmt(abs($difference)).' short of user liabilities. Reconcile immediately.'];
        }

        $e['notes'][] = 'Balances are point-in-time (recomputed from the ledger) and are not affected by the date filter.';

        return $e;
    }
}
