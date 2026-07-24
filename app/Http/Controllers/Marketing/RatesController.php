<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domain\Exchange\CoinGeckoRateProvider;
use App\Http\Controllers\Controller;
use App\Support\BaseCurrency;
use Illuminate\Http\JsonResponse;

/**
 * Public crypto→fiat reference rates for the marketing converter / prices page,
 * expressed in the viewer's base currency (signed-in user's choice, else USD).
 * Display only (labelled "reference rate, not a quote") — the authenticated
 * exchange prices its own quotes with spread via ExchangeService.
 */
class RatesController extends Controller
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
