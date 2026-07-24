<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Domain\Exchange\CoinGeckoRateProvider;
use App\Http\Controllers\Controller;
use App\Support\BaseCurrency;
use Illuminate\View\View;

/**
 * Public live crypto→BDT prices page. Renders server-side with the latest
 * reference rates, then refreshes in place from {@see RatesController} (the
 * /rates JSON feed) every minute. Display only — not a tradable quote.
 */
class PricesController extends Controller
{
    /** Symbol => display name, in presentation order. */
    private const COINS = [
        'BTC' => 'Bitcoin',
        'ETH' => 'Ethereum',
        'USDT' => 'Tether',
        'USDC' => 'USD Coin',
        'BNB' => 'BNB',
        'TRX' => 'TRON',
        'TON' => 'Toncoin',
    ];

    public function __invoke(CoinGeckoRateProvider $rates): View
    {
        $base = BaseCurrency::displayCode();

        return view('marketing.prices', [
            'coins' => self::COINS,
            'rates' => $rates->ratesWithFallback($base, array_keys(self::COINS)),
            'base' => $base,
            'symbol' => BaseCurrency::symbol($base),
            'asOf' => now(),
        ]);
    }
}
