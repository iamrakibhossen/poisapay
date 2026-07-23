<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domain\Exchange\CoinGeckoRateProvider;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public crypto→BDT reference rates for the marketing converter. Display only
 * (labelled "reference rate, not a quote") — the authenticated exchange prices
 * its own quotes with spread via ExchangeService.
 */
class RatesController extends Controller
{
    public function __invoke(CoinGeckoRateProvider $rates): JsonResponse
    {
        $symbols = ['USDT', 'USDC', 'ETH', 'BTC', 'BNB', 'TON'];

        return response()
            ->json([
                'base' => 'BDT',
                'rates' => $rates->bdtRatesWithFallback($symbols),
                'as_of' => now()->toIso8601String(),
            ])
            ->header('Cache-Control', 'public, max-age=30');
    }
}
