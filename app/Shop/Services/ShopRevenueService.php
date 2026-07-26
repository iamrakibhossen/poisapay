<?php

declare(strict_types=1);

namespace App\Shop\Services;

use App\Enums\LedgerAccountType;
use App\Enums\LedgerSide;
use App\Models\Asset;
use App\Support\Money;
use Brick\Math\BigInteger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read model for Shop revenue. Every figure is DERIVED from the double-entry
 * ledger — never from denormalized order columns — so the Shop dashboards and the
 * platform revenue view agree with the ledger by construction.
 *
 * The two Shop entry types tell the whole story:
 *   - `shop.purchase`  DR buyer user:available (total)
 *                      CR seller user:available|locked (net)
 *                      CR shop:commission_income (platform cut)
 *   - `shop.refund`    CR buyer user:available (amount)
 *                      DR seller user:available|locked (seller share)
 *                      DR shop:commission_income (commission share)
 *
 * From those, GMV is the buyer debits, refunds the buyer credits, platform
 * commission the net of the commission account, and merchant earnings the net
 * of the seller credits — all pro-rata correct because refunds post the reverse.
 */
class ShopRevenueService
{
    private const PURCHASE = 'shop.purchase';

    private const REFUND = 'shop.refund';

    /** The user balance buckets a Shop settlement can land in (spendable / held). */
    private const USER_BUCKETS = [LedgerAccountType::UserAvailable, LedgerAccountType::UserLocked];

    /** Gross merchandise value — everything buyers paid (before refunds). */
    public function gmv(Asset $asset, ?CarbonImmutable $since = null, ?CarbonImmutable $until = null): Money
    {
        return $this->money($asset, $this->sum([self::PURCHASE], self::USER_BUCKETS, LedgerSide::Debit, $asset->id, $since, $until));
    }

    /** Money returned to buyers via refunds. */
    public function refunded(Asset $asset, ?CarbonImmutable $since = null, ?CarbonImmutable $until = null): Money
    {
        return $this->money($asset, $this->sum([self::REFUND], self::USER_BUCKETS, LedgerSide::Credit, $asset->id, $since, $until));
    }

    /** Net sales = GMV minus refunds. */
    public function netSales(Asset $asset, ?CarbonImmutable $since = null, ?CarbonImmutable $until = null): Money
    {
        $gmv = $this->sum([self::PURCHASE], self::USER_BUCKETS, LedgerSide::Debit, $asset->id, $since, $until);
        $refunded = $this->sum([self::REFUND], self::USER_BUCKETS, LedgerSide::Credit, $asset->id, $since, $until);

        return $this->money($asset, $gmv->minus($refunded));
    }

    /**
     * Platform commission earned, NET of refund clawbacks — the true shop revenue.
     * Equal to the balance of the commission-income account when no window is given.
     */
    public function commission(Asset $asset, ?CarbonImmutable $since = null, ?CarbonImmutable $until = null): Money
    {
        $earned = $this->sum([self::PURCHASE], [LedgerAccountType::ShopCommissionIncome], LedgerSide::Credit, $asset->id, $since, $until);
        $back = $this->sum([self::REFUND], [LedgerAccountType::ShopCommissionIncome], LedgerSide::Debit, $asset->id, $since, $until);

        return $this->money($asset, $earned->minus($back));
    }

    /** Merchant earnings, NET of refund clawbacks — what sellers collectively kept. */
    public function merchantEarnings(Asset $asset, ?CarbonImmutable $since = null, ?CarbonImmutable $until = null): Money
    {
        $credited = $this->sum([self::PURCHASE], self::USER_BUCKETS, LedgerSide::Credit, $asset->id, $since, $until);
        $clawed = $this->sum([self::REFUND], self::USER_BUCKETS, LedgerSide::Debit, $asset->id, $since, $until);

        return $this->money($asset, $credited->minus($clawed));
    }

    /**
     * Headline figures for a dashboard.
     *
     * @return array{gmv: Money, net_sales: Money, refunded: Money, commission: Money, merchant_earnings: Money}
     */
    public function summary(Asset $asset, ?CarbonImmutable $since = null, ?CarbonImmutable $until = null): array
    {
        return [
            'gmv' => $this->gmv($asset, $since, $until),
            'net_sales' => $this->netSales($asset, $since, $until),
            'refunded' => $this->refunded($asset, $since, $until),
            'commission' => $this->commission($asset, $since, $until),
            'merchant_earnings' => $this->merchantEarnings($asset, $since, $until),
        ];
    }

    /** @return array{today: Money, week: Money, month: Money, lifetime: Money} GMV over standard windows. */
    public function gmvStats(Asset $asset): array
    {
        $now = CarbonImmutable::now();

        return [
            'today' => $this->gmv($asset, $now->startOfDay()),
            'week' => $this->gmv($asset, $now->startOfWeek()),
            'month' => $this->gmv($asset, $now->startOfMonth()),
            'lifetime' => $this->gmv($asset),
        ];
    }

    /** Daily net-sales series for a chart. @return array<int, array{label: string, value: float}> */
    public function dailyNetSalesSeries(Asset $asset, int $days = 14): array
    {
        return collect(range($days - 1, 0))->map(function (int $d) use ($asset) {
            $day = CarbonImmutable::today()->subDays($d);

            return ['label' => $day->format('M j'), 'value' => (float) $this->netSales($asset, $day, $day->addDay())->toDecimal()];
        })->all();
    }

    private function money(Asset $asset, BigInteger $base): Money
    {
        return Money::ofBase((string) $base, $asset->decimals, $asset->symbol);
    }

    /**
     * Sum ledger-line amounts for the given entry types, account types and side.
     *
     * @param  array<int, string>  $entryTypes
     * @param  array<int, LedgerAccountType>  $accountTypes
     */
    private function sum(array $entryTypes, array $accountTypes, LedgerSide $side, int $assetId, ?CarbonImmutable $since, ?CarbonImmutable $until): BigInteger
    {
        $q = $this->linesQuery($entryTypes, $accountTypes, $side, $assetId);
        if ($since) {
            $q->where('e.created_at', '>=', $since);
        }
        if ($until) {
            $q->where('e.created_at', '<', $until);
        }

        return BigInteger::of((string) ($q->sum('l.amount') ?? '0'));
    }

    /**
     * @param  array<int, string>  $entryTypes
     * @param  array<int, LedgerAccountType>  $accountTypes
     */
    private function linesQuery(array $entryTypes, array $accountTypes, LedgerSide $side, int $assetId): Builder
    {
        return DB::table('ledger_lines as l')
            ->join('ledger_accounts as a', 'a.id', '=', 'l.account_id')
            ->join('journal_entries as e', 'e.id', '=', 'l.entry_id')
            ->whereIn('e.type', $entryTypes)
            ->whereIn('a.type', array_map(fn (LedgerAccountType $t) => $t->value, $accountTypes))
            ->where('l.side', $side->value)
            ->where('l.asset_id', $assetId);
    }
}
