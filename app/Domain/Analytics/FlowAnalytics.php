<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use App\Domain\Exchange\Contracts\RateProvider;
use App\Models\Asset;
use App\Models\Deposit;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Rolling money-flow analytics for a user, valued in their base currency.
 * Single source of truth shared by the wallet and dashboard so the inflow /
 * outflow / net figures never drift between the two.
 */
class FlowAnalytics
{
    public function __construct(private readonly RateProvider $rates) {}

    /**
     * Inflow / outflow / net over the trailing $days window (base currency).
     *
     * @return array{inflow: string, outflow: string, net: string, netPositive: bool}
     */
    public function forUser(User $user, int $days = 30): array
    {
        $base = Asset::where('currency_code', $user->base_currency)->first() ?? Asset::where('symbol', 'BDT')->first();
        $since = now()->subDays($days);
        $userId = $user->id;

        $inflow = BigDecimal::zero();
        $outflow = BigDecimal::zero();

        $toFiat = function (Asset $asset, string $baseUnits) use ($base): BigDecimal {
            if (! $base) {
                return BigDecimal::zero();
            }

            return BigDecimal::ofUnscaledValue($baseUnits, $asset->decimals)->multipliedBy($this->rates->rate($asset, $base));
        };

        foreach (Deposit::with('asset')->where('user_id', $userId)->where('created_at', '>=', $since)->where('status', 'credited')->get() as $d) {
            $inflow = $inflow->plus($toFiat($d->asset, $d->amount));
        }
        foreach (Withdrawal::with('asset')->where('user_id', $userId)->where('created_at', '>=', $since)->get() as $w) {
            $outflow = $outflow->plus($toFiat($w->asset, $w->amount));
        }
        foreach (Transfer::with('asset')->where('created_at', '>=', $since)
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('recipient_id', $userId))->get() as $t) {
            $val = $toFiat($t->asset, $t->amount);
            $t->sender_id === $userId ? $outflow = $outflow->plus($val) : $inflow = $inflow->plus($val);
        }

        $fmt = fn (BigDecimal $v) => $base
            ? Money::ofBase($v->withPointMovedRight($base->decimals)->toScale(0, RoundingMode::DOWN)->toBigInteger(), $base->decimals, $base->symbol)->format(2)
            : '—';

        return [
            'inflow' => $fmt($inflow),
            'outflow' => $fmt($outflow),
            'net' => $fmt($inflow->minus($outflow)),
            'netPositive' => $inflow->isGreaterThanOrEqualTo($outflow),
        ];
    }
}
