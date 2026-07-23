<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Analytics\FlowAnalytics;
use App\Domain\Exchange\Contracts\RateProvider;
use App\Domain\Wallet\WalletService;
use App\Enums\CardStatus;
use App\Enums\KycTier;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Card;
use App\Models\Deposit;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Dashboard page — server-rendered. The controller builds the portfolio
 * summary, allocation chart config and recent activity and passes them straight
 * to the Blade view. The donut chart is rendered by the global Alpine
 * `x-data="chart(config)"` component with a server-computed config.
 */
class DashboardController extends Controller
{
    /** Asset brand colours (match the asset-icon palette). */
    private const PALETTE = [
        'USDT' => '#10b981', 'USDC' => '#0ea5e9', 'ETH' => '#6366f1', 'BNB' => '#f59e0b',
        'TRX' => '#f43f5e', 'BTC' => '#f97316', 'BDT' => '#16a34a', 'USD' => '#475569', 'EUR' => '#2563eb',
    ];

    public function index(Request $request, WalletService $wallets): View
    {
        $user = $request->user();

        // Value the portfolio off the system rate provider (live CoinGecko by
        // default, per config/providers.php) so the numbers the page loads with
        // match the ones the live poll refreshes them to.
        $snap = $this->snapshot($user, $wallets, app(RateProvider::class));
        $rows = $snap['rows'];
        $base = $snap['base'];
        $totalBase = $snap['totalBase'];
        $lockedBase = $snap['lockedBase'];
        $assetCount = $snap['assetCount'];

        $funded = $rows->map(fn ($r) => [
            'symbol' => $r['symbol'],
            'available' => $r['available'],
            'fiat' => $r['fiat'],
            'share' => $r['share'],
            'color' => $r['color'],
        ])->all();

        $allocationValues = $rows->map(fn ($r) => (float) (string) $r['fiatValue']->toScale(2, RoundingMode::HALF_UP))->all();
        $allocationConfig = [
            'type' => 'doughnut',
            'data' => [
                'labels' => $rows->pluck('symbol')->all(),
                'datasets' => [[
                    'data' => $allocationValues,
                    'backgroundColor' => $rows->map(fn ($r) => self::PALETTE[$r['symbol']] ?? '#94a3b8')->all(),
                    'borderWidth' => 0,
                ]],
            ],
            'options' => [
                'cutout' => '70%',
                'plugins' => ['legend' => ['display' => false], 'tooltip' => ['enabled' => true]],
                'responsive' => true,
                'maintainAspectRatio' => false,
            ],
        ];

        // Primary card for the right-side preview: prefer an active card, else the newest.
        $card = Card::where('user_id', $user->id)
            ->where('status', '!=', CardStatus::Closed->value)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [CardStatus::Active->value])
            ->latest()
            ->first();

        return view('frontend.dashboard', [
            'firstName' => str($user->name)->explode(' ')->first(),
            'card' => $card,
            'holderName' => $user->name,
            'needsKyc' => $user->tier() !== KycTier::Full,
            'funded' => $funded,
            'assetCount' => $assetCount,
            'baseCurrency' => $base?->symbol ?? 'BDT',
            'portfolioValue' => $base ? $this->fmt($totalBase, $base) : '—',
            'lockedValue' => $base ? $this->fmt($lockedBase, $base) : '—',
            'hasLocked' => ! $lockedBase->isZero(),
            'allocationConfig' => $allocationConfig,
            'analytics' => app(FlowAnalytics::class)->forUser($user),
            'recent' => $this->recentActivity($user->id),
            'pendingDeposits' => Deposit::where('user_id', $user->id)->whereIn('status', ['detected', 'confirming'])->count(),
            'pendingWithdrawals' => Withdrawal::where('user_id', $user->id)->whereIn('status', ['pending', 'review', 'approved', 'signing', 'broadcast'])->count(),
        ]);
    }

    /**
     * Live portfolio values as JSON — polled client-side so the dashboard's
     * currency figures tick without a page reload (parity with the homepage
     * converter). Recomputes off the same live feed as {@see index()}.
     */
    public function live(Request $request, WalletService $wallets): JsonResponse
    {
        $user = $request->user();
        $snap = $this->snapshot($user, $wallets, app(RateProvider::class));
        $base = $snap['base'];

        return response()->json([
            'base' => $base?->symbol ?? 'BDT',
            'portfolioValue' => $base ? $this->fmt($snap['totalBase'], $base) : '—',
            'portfolioRaw' => (float) (string) $snap['totalBase']->toScale(2, RoundingMode::HALF_UP),
            'lockedValue' => $base ? $this->fmt($snap['lockedBase'], $base) : '—',
            'hasLocked' => ! $snap['lockedBase']->isZero(),
            'assets' => $snap['rows']->map(fn ($r) => [
                'symbol' => $r['symbol'],
                'fiat' => $r['fiat'],
                'share' => $r['share'],
            ])->all(),
            'asOf' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }

    /**
     * Shared portfolio computation for {@see index()} and {@see live()}: funded
     * wallets valued in the user's base currency, with per-asset share + colour.
     *
     * @return array{rows: Collection, totalBase: BigDecimal, lockedBase: BigDecimal, base: ?Asset, assetCount: int}
     */
    private function snapshot(User $user, WalletService $wallets, RateProvider $rates): array
    {
        $funded = $wallets->walletsFor($user)->filter(fn ($b) => ! $b->total()->isZero())->values();
        $base = Asset::where('currency_code', $user->base_currency)->first() ?? Asset::where('symbol', 'BDT')->first();

        $totalBase = BigDecimal::zero();
        $lockedBase = BigDecimal::zero();

        $rows = $funded->map(function ($b) use ($rates, $base, &$totalBase, &$lockedBase) {
            $value = BigDecimal::zero();
            $lockedValue = BigDecimal::zero();
            if ($base) {
                $rate = $rates->rate($b->asset, $base);
                $value = BigDecimal::ofUnscaledValue($b->total()->baseString(), $b->asset->decimals)->multipliedBy($rate);
                $lockedValue = BigDecimal::ofUnscaledValue($b->locked->baseString(), $b->asset->decimals)->multipliedBy($rate);
                $totalBase = $totalBase->plus($value);
                $lockedBase = $lockedBase->plus($lockedValue);
            }

            return [
                'symbol' => $b->asset->symbol,
                'available' => $b->available->format(),
                'fiatValue' => $value,
                'fiat' => $base ? $this->fmt($value, $base) : null,
            ];
        });

        // Per-asset share for the allocation chart + bars.
        $total = $totalBase->isZero() ? BigDecimal::one() : $totalBase;
        $rows = $rows->map(function ($row) use ($total) {
            $share = $row['fiatValue']->dividedBy($total, 4, RoundingMode::DOWN)->multipliedBy(100);
            $row['share'] = (float) (string) $share->toScale(1, RoundingMode::HALF_UP);
            $row['color'] = self::PALETTE[$row['symbol']] ?? '#94a3b8';

            return $row;
        })->sortByDesc('share')->values();

        return [
            'rows' => $rows,
            'totalBase' => $totalBase,
            'lockedBase' => $lockedBase,
            'base' => $base,
            'assetCount' => $funded->count(),
        ];
    }

    private function recentActivity(string $userId): Collection
    {
        $deposits = Deposit::with('asset')->where('user_id', $userId)->latest()->limit(5)->get()
            ->map(fn ($d) => [
                'icon' => 'arrow-down-left', 'color' => 'success',
                'title' => $d->asset->symbol.' deposit', 'amount' => '+'.$d->money()->format(),
                'status' => $d->status->label(), 'at' => $d->created_at,
                'at_human' => $d->created_at->diffForHumans(),
            ]);

        $withdrawals = Withdrawal::with('asset')->where('user_id', $userId)->latest()->limit(5)->get()
            ->map(fn ($w) => [
                'icon' => 'arrow-up-right', 'color' => 'warning',
                'title' => $w->asset->symbol.' withdrawal', 'amount' => '-'.$w->money()->format(),
                'status' => $w->status->label(), 'at' => $w->created_at,
                'at_human' => $w->created_at->diffForHumans(),
            ]);

        $transfers = Transfer::with('asset')->where('sender_id', $userId)->orWhere('recipient_id', $userId)
            ->latest()->limit(5)->get()
            ->map(fn ($t) => [
                'icon' => 'paper-airplane', 'color' => 'info',
                'title' => ($t->sender_id === $userId ? 'Sent ' : 'Received ').$t->asset->symbol,
                'amount' => ($t->sender_id === $userId ? '-' : '+').$t->money()->format(),
                'status' => $t->status->label(), 'at' => $t->created_at,
                'at_human' => $t->created_at->diffForHumans(),
            ]);

        return $deposits->concat($withdrawals)->concat($transfers)->sortByDesc('at')->take(6)->values();
    }

    private function fmt(BigDecimal $whole, Asset $asset): string
    {
        $baseUnits = $whole->withPointMovedRight($asset->decimals)->toScale(0, RoundingMode::DOWN)->toBigInteger();

        return Money::ofBase($baseUnits, $asset->decimals, $asset->symbol)->format(2);
    }
}
