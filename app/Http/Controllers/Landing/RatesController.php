<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landing;

use App\Domain\Exchange\CoinGeckoRateProvider;
use App\Http\Controllers\Controller;
use App\Support\BaseCurrency;
use Illuminate\Http\JsonResponse;

/**
 * Public crypto→fiat reference rates for the landing converter / prices page,
 * expressed in the viewer's base currency (signed-in user's choice, else USD).
 * Display only (labelled "reference rate, not a quote").
 */
final class RatesController extends Controller
{
    public function __invoke(CoinGeckoRateProvider $rates): JsonResponse
    {
        $symbols = ['BTC', 'ETH', 'USDT', 'USDC', 'BNB', 'TRX', 'TON'];
        $base = BaseCurrency::displayCode();

        return response()
            ->json([
                'base' => $base,
                'symbol' => BaseCurrency::symbol($base),
                'rates' => $rates->ratesWithFallback($base, $symbols),
                'as_of' => now()->toIso8601String(),
            ])
            ->header('Cache-Control', 'private, max-age=30');
    }
}
