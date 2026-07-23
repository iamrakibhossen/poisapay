<?php

declare(strict_types=1);

namespace App\Http\Controllers\Frontend;

use App\Domain\Analytics\FlowAnalytics;
use App\Domain\Exchange\Contracts\RateProvider;
use App\Domain\Wallet\WalletService;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Wallet page — server-rendered. The controller builds the balance list,
 * portfolio value and 30-day analytics and passes them straight to the Blade
 * view. Filtering/searching is done with plain Blade + a GET query string.
 */
class WalletController extends Controller
{
    public function index(Request $request, WalletService $wallets): View
    {
        $user = $request->user();
        // One wallet per coin (WalletService pools per currency).
        $all = $wallets->walletsFor($user);
        $favorites = $user->favoriteAssets()->pluck('assets.id')->all();

        $base = $this->baseAsset($user);
        $rates = app(RateProvider::class);

        // Number of settlement networks per coin (for the "N networks" hint).
        $networkCounts = Asset::where('is_active', true)
            ->selectRaw('currency_id, count(*) as c')->groupBy('currency_id')->pluck('c', 'currency_id');

        $total = BigDecimal::zero();

        $rows = $all->map(function ($b) use ($favorites, $base, $rates, $networkCounts, &$total) {
            $asset = $b->asset;
            $value = $base
                ? BigDecimal::ofUnscaledValue($b->total()->baseString(), $asset->decimals)->multipliedBy($rates->rate($asset, $base))
                : BigDecimal::zero();
            $total = $total->plus($value);
            $funded = ! $b->total()->isZero();

            return [
                'assetId' => $asset->id,            // representative (canonical) network
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'isFiat' => $asset->isFiat(),
                'networks' => (int) ($networkCounts[$asset->currency_id] ?? 1),
                'available' => $b->available->format(),
                'locked' => $b->locked->isZero() ? null : $b->locked->format(),
                'fiatValue' => $funded && $base ? $this->fiat($value, $base) : null,
                'favorite' => in_array($asset->id, $favorites, true),
                'funded' => $funded,
            ];
        })
            // Favorites first, then funded, then by symbol.
            ->sort(function ($a, $b) {
                $fa = $a['favorite'] ? 0 : 1;
                $fb = $b['favorite'] ? 0 : 1;
                if ($fa !== $fb) {
                    return $fa <=> $fb;
                }
                $na = $a['funded'] ? 0 : 1;
                $nb = $b['funded'] ? 0 : 1;
                if ($na !== $nb) {
                    return $na <=> $nb;
                }

                return strcmp($a['symbol'], $b['symbol']);
            })
            ->values()
            ->all();

        $filter = (string) $request->query('filter', 'all');
        $search = trim((string) $request->query('search', ''));

        return view('frontend.wallet', [
            'wallets' => $rows,
            'totalValue' => $base ? $this->fiat($total, $base) : '—',
            'fundedCount' => collect($rows)->where('funded', true)->count(),
            'totalAssets' => count($rows),
            'analytics' => app(FlowAnalytics::class)->forUser($user),
            'filter' => in_array($filter, ['all', 'crypto', 'fiat'], true) ? $filter : 'all',
            'search' => $search,
        ]);
    }

    /** Star / unstar an asset (favorite wallets). */
    public function toggleFavorite(Request $request, int $asset): RedirectResponse
    {
        $request->user()->favoriteAssets()->toggle($asset);

        return redirect()->back();
    }

    /** The user's base-currency asset for fiat valuation (falls back to BDT). */
    private function baseAsset($user): ?Asset
    {
        return Asset::where('currency_code', $user->base_currency)->first() ?? Asset::where('symbol', 'BDT')->first();
    }

    private function fiat(BigDecimal $value, Asset $base): string
    {
        return Money::ofBase(
            $value->withPointMovedRight($base->decimals)->toScale(0, RoundingMode::DOWN)->toBigInteger(),
            $base->decimals,
            $base->symbol,
        )->format(2);
    }

    /**
     * 30-day inflow/outflow in the user's base currency (wallet analytics).
     *
     * @return array{inflow: string, outflow: string, net: string, netPositive: bool}
     */
}
