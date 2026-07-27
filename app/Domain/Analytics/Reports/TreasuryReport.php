<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\LedgerAggregates;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Enums\LedgerAccountType as T;

/**
 * Treasury & proof-of-reserve analytics: hot/cold split, reserve ratio against
 * user liabilities, and a movement timeline. Low-reserve and insolvency states
 * surface as alerts.
 */
class TreasuryReport extends Report
{
    private const USER_FUNDS = [T::UserAvailable, T::UserLocked, T::UserCardHold, T::UserP2pEscrow, T::UserCollateralLocked];

    public function __construct(private readonly LedgerAggregates $ledger) {}

    public function key(): string
    {
        return 'treasury';
    }

    public function title(): string
    {
        return 'Treasury Analytics';
    }

    public function build(Period $period): array
    {
        $e = $this->envelope();

        // Custody (assets) — real on-chain/bank holdings only. Never moved by swaps.
        $hot = (float) $this->ledger->usdTotal([T::TreasuryHot]);
        $cold = (float) $this->ledger->usdTotal([T::TreasuryCold]);
        $pending = (float) $this->ledger->usdTotal([T::TreasuryPending]);
        $reserve = $hot + $cold + $pending;

        // Customer liabilities (what we owe users) and the house dealer position (equity).
        $liabilities = (float) $this->ledger->usdTotal(self::USER_FUNDS);
        $inventoryByAsset = collect($this->ledger->usdByAsset([T::TradingInventory]));
        $available = (float) $inventoryByAsset->where('usd', '>', 0)->sum('usd'); // deliverable liquidity
        $netPosition = (float) $inventoryByAsset->sum('usd');                     // signed long/short
        $shorts = $inventoryByAsset->where('usd', '<', 0);

        $ratio = $liabilities > 0 ? round(($reserve / $liabilities) * 100, 1) : 100.0;
        $coldPct = $reserve > 0 ? round(($cold / $reserve) * 100, 1) : 0.0;

        $e['kpis'] = [
            $this->kpi('On-chain Treasury Assets', $this->usdFmt($reserve), ['accent' => 'brand', 'icon' => 'building-library', 'hint' => 'Hot + cold + pending custody']),
            $this->kpi('Customer Liabilities', $this->usdFmt($liabilities), ['accent' => 'violet', 'icon' => 'users', 'hint' => 'Owed to customers']),
            $this->kpi('Available Liquidity', $this->usdFmt($available), ['accent' => $available > 0 ? 'emerald' : 'rose', 'icon' => 'beaker', 'hint' => 'Dealer inventory to fill swaps']),
            $this->kpi('Reserve Ratio', $ratio.'%', ['accent' => $ratio >= 100 ? 'emerald' : 'rose', 'hint' => 'Assets ÷ liabilities']),
            $this->kpi('Dealer Net Position', $this->usdFmt($netPosition), ['accent' => $netPosition >= 0 ? 'sky' : 'rose', 'icon' => 'scale', 'hint' => 'House long/short across assets']),
            $this->kpi('Hot Wallet', $this->usdFmt($hot), ['accent' => 'rose', 'icon' => 'fire']),
            $this->kpi('Cold Wallet', $this->usdFmt($cold), ['accent' => 'sky', 'icon' => 'lock-closed']),
            $this->kpi('Cold Storage %', $coldPct.'%', ['accent' => $coldPct >= 50 ? 'emerald' : 'amber', 'hint' => 'Share in cold custody']),
        ];

        $e['charts'][] = $this->chart('treasury-hotcold', 'Reserve composition', 'doughnut',
            ['Hot', 'Cold', 'Pending'],
            [$this->dataset('USD', [$hot, $cold, $pending])],
            ['span' => 'half']);

        // Dealer long/short position by asset (positive = long, negative = short).
        if ($inventoryByAsset->isNotEmpty()) {
            $e['charts'][] = $this->chart('dealer-position', 'Dealer position by asset', 'bar',
                $inventoryByAsset->pluck('symbol')->all(),
                [$this->dataset('USD', $inventoryByAsset->pluck('usd')->all(), '#8b5cf6')],
                ['span' => 'half']);

            $e['tables'][] = [
                'title' => 'TradingInventory by asset',
                'headers' => ['Asset', 'Inventory', 'USD value', 'Position'],
                'align' => ['left', 'right', 'right', 'right'],
                'rows' => $inventoryByAsset->map(fn ($r) => [
                    $r['symbol'], $r['money'], $this->usdFmt($r['usd']),
                    $r['usd'] > 0 ? 'Long' : ($r['usd'] < 0 ? 'Short' : 'Flat'),
                ])->all(),
            ];
        }

        $byAsset = collect($this->ledger->usdByAsset([T::TreasuryHot, T::TreasuryCold, T::TreasuryPending]));
        if ($byAsset->isNotEmpty()) {
            $e['charts'][] = $this->chart('treasury-by-asset', 'Custody by asset', 'bar',
                $byAsset->pluck('symbol')->all(),
                [$this->dataset('USD', $byAsset->pluck('usd')->all(), '#0ea5e9')],
                ['span' => 'half']);
        }

        // Movement timeline: count of ledger lines touching treasury accounts.
        $movement = $this->series(
            $this->fresh('ledger_lines as l')
                ->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')
                ->whereIn('a.type', [T::TreasuryHot->value, T::TreasuryCold->value, T::TreasuryPending->value, T::TreasuryOut->value]),
            $period, 'l.created_at');
        $e['charts'][] = $this->chart('treasury-movement', 'Treasury movement (ledger postings)', 'area',
            $this->bucketLabels($period), [$this->dataset('Postings', $movement, '#d97706')]);

        // ── Proof-of-reserve ──
        if ($reserve < $liabilities) {
            $e['alerts'][] = ['level' => 'critical', 'title' => 'Reserve below liabilities',
                'message' => 'Proof-of-reserve failing: assets '.$this->usdFmt($reserve).' < liabilities '.$this->usdFmt($liabilities).'.'];
        } elseif ($ratio < 105) {
            $e['alerts'][] = ['level' => 'warn', 'title' => 'Thin reserve buffer',
                'message' => 'Reserve ratio is '.$ratio.'% — consider topping up cold storage.'];
        }

        // ── Liquidity ──
        foreach ($shorts as $s) {
            $e['alerts'][] = ['level' => 'critical', 'title' => "Dealer short in {$s['symbol']}",
                'message' => "TradingInventory for {$s['symbol']} is negative ({$this->usdFmt($s['usd'])}) — the house is oversold. Rebalance or inject liquidity."];
        }
        if ($available <= 0 && $liabilities > 0) {
            $e['alerts'][] = ['level' => 'warn', 'title' => 'No dealer liquidity',
                'message' => 'Available liquidity is $0 — swaps will be rejected until inventory is injected (paishapay:inject-inventory).'];
        }

        $e['notes'][] = 'TradingInventory is an equity account (house dealer position), not custody. On-chain custody (hot/cold/pending) only ever moves on real chain/bank events, so it reconciles 1:1 to the wallets.';

        return $e;
    }
}
