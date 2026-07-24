<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Reports;

use App\Domain\Analytics\LedgerAggregates;
use App\Domain\Analytics\Period;
use App\Domain\Analytics\Report;
use App\Enums\LedgerAccountType as T;
use App\Models\Card;
use App\Models\CardAuthorization;
use Illuminate\Support\Facades\DB;

/**
 * Card program analytics: portfolio state, authorisation success rate, cardholder
 * spend and fee revenue. Spend is in settlement minor units (USD-denominated).
 */
class CardReport extends Report
{
    public function __construct(private readonly LedgerAggregates $ledger) {}

    public function key(): string
    {
        return 'cards';
    }

    public function title(): string
    {
        return 'Card Analytics';
    }

    public function build(Period $period): array
    {
        $e = $this->envelope();
        $prev = $period->previous();

        $virtual = Card::where('type', 'virtual')->count();
        $physical = Card::where('type', 'physical')->count();
        $created = Card::whereBetween('created_at', [$period->start, $period->end])->count();
        $createdPrev = Card::whereBetween('created_at', [$prev->start, $prev->end])->count();
        $frozen = Card::where('status', 'frozen')->count();
        $closed = Card::where('status', 'closed')->count();

        $auths = CardAuthorization::whereBetween('created_at', [$period->start, $period->end]);
        $authTotal = (clone $auths)->count();
        $approved = (clone $auths)->whereIn('status', ['approved', 'settled'])->count();
        $successRate = $authTotal > 0 ? round(($approved / $authTotal) * 100, 1) : 0.0;

        // Spend = settled authorisations, settlement minor units → USD.
        $spendMinor = (float) CardAuthorization::where('status', 'settled')->whereBetween('created_at', [$period->start, $period->end])->sum('amount');
        $spend = $spendMinor / 100;
        $feeRevenue = (float) $this->ledger->usdTotal([T::FeeCard], $period);

        $e['kpis'] = [
            $this->kpi('Virtual Cards', number_format($virtual), ['accent' => 'brand', 'icon' => 'credit-card']),
            $this->kpi('Physical Cards', number_format($physical), ['accent' => 'sky']),
            $this->trendKpi('Cards Created', number_format($created), $created, $createdPrev, ['accent' => 'emerald']),
            $this->kpi('Frozen', number_format($frozen), ['accent' => 'amber']),
            $this->kpi('Closed', number_format($closed), ['accent' => 'rose']),
            $this->kpi('Card Spend', $this->usdFmt($spend), ['accent' => 'emerald', 'icon' => 'banknotes']),
            $this->kpi('Card Fee Revenue', $this->usdFmt($feeRevenue), ['accent' => 'brand']),
            $this->kpi('Auth Success Rate', $successRate.'%', ['accent' => $successRate >= 90 ? 'emerald' : 'amber']),
        ];

        $e['charts'][] = $this->chart('card-created-trend', 'Cards created', 'area',
            $this->bucketLabels($period), [$this->dataset('Cards', $this->series(DB::table('cards'), $period), '#d97706')]);

        $authByStatus = DB::table('card_authorizations')->whereBetween('created_at', [$period->start, $period->end])
            ->groupBy('status')->selectRaw('status, count(*) as c')->pluck('c', 'status');
        if ($authByStatus->isNotEmpty()) {
            $e['charts'][] = $this->chart('card-auth-status', 'Authorisations by status', 'doughnut',
                $authByStatus->keys()->map(fn ($s) => ucfirst((string) $s))->all(),
                [$this->dataset('Count', $authByStatus->values()->map(fn ($c) => (int) $c)->all())], ['span' => 'half']);
        }

        $topMerchants = DB::table('card_authorizations')->whereBetween('created_at', [$period->start, $period->end])
            ->whereNotNull('merchant')->groupBy('merchant')
            ->selectRaw('merchant, count(*) as c, sum(amount) as vol')->orderByDesc('vol')->limit(10)->get();
        if ($topMerchants->isNotEmpty()) {
            $e['tables'][] = [
                'title' => 'Top merchants',
                'headers' => ['Merchant', 'Transactions', 'Spend'],
                'align' => ['left', 'right', 'right'],
                'rows' => $topMerchants->map(fn ($m) => [$m->merchant, number_format((int) $m->c), $this->usdFmt(((float) $m->vol) / 100)])->all(),
            ];
        }

        $e['notes'][] = 'Card usage heatmap (by hour/day) is available as a follow-up; authorisation timestamps support it but it is not rendered yet.';

        return $e;
    }
}
