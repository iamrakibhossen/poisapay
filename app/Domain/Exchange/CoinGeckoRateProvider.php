<?php

declare(strict_types=1);

namespace App\Domain\Exchange;

use App\Domain\Exchange\Contracts\RateProvider;
use App\Models\Asset;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Live crypto/fiat rates from CoinGecko's public API (§F2.1 live feed).
 * Implements the same RateProvider contract as the stub, so it can back the
 * whole exchange (RATES_DRIVER=coingecko) or be used directly for display
 * (the marketing converter). Every failure path degrades to the deterministic
 * stub / indicative values, so callers never break when the feed is down.
 */
final class CoinGeckoRateProvider implements RateProvider
{
    private const ENDPOINT = 'https://api.coingecko.com/api/v3/simple/price';

    /** App symbol => CoinGecko coin id. */
    private const IDS = [
        'USDT' => 'tether',
        'USDC' => 'usd-coin',
        'ETH' => 'ethereum',
        'BTC' => 'bitcoin',
        'BNB' => 'binancecoin',
        'TON' => 'the-open-network',
        'TRX' => 'tron',
    ];

    /** Fiat currencies the live feed and fallbacks can express crypto prices in. */
    public const FIATS = ['USD', 'BDT', 'EUR'];

    /** Indicative BDT prices used when the live feed is unavailable. */
    public const FALLBACK_BDT = [
        'USDT' => '121.50',
        'USDC' => '121.45',
        'ETH' => '402150',
        'BTC' => '7250000',
        'BNB' => '52000',
        'TON' => '385',
    ];

    /** Indicative USD prices — cross-multiplied for any non-BDT fiat when offline. */
    public const FALLBACK_USD = [
        'USDT' => '1.00',
        'USDC' => '1.00',
        'ETH' => '3300',
        'BTC' => '60000',
        'BNB' => '430',
        'TON' => '3.20',
        'TRX' => '0.13',
    ];

    /** Approximate units of each fiat per 1 USD (offline cross only). */
    private const FIAT_PER_USD = ['USD' => '1', 'BDT' => '121.5', 'EUR' => '0.92'];

    public function __construct(private readonly StubRateProvider $fallback) {}

    /** Mid-market price of 1 unit of $from expressed in units of $to. */
    public function rate(Asset $from, Asset $to): BigDecimal
    {
        $usd = $this->data()['usd'] ?? [];
        $fromUsd = $usd[$from->symbol] ?? $usd[$from->currency_code] ?? null;
        $toUsd = $usd[$to->symbol] ?? $usd[$to->currency_code] ?? null;

        if ($fromUsd === null || $toUsd === null) {
            return $this->fallback->rate($from, $to);   // symbol absent from the live feed
        }

        $toBd = BigDecimal::of((string) $toUsd);
        if ($toBd->isZero()) {
            return BigDecimal::zero();
        }

        return BigDecimal::of((string) $fromUsd)->dividedBy($toBd, 18, RoundingMode::DOWN);
    }

    /**
     * symbol => string rate in $base fiat for the given crypto symbols — live when
     * the feed carries that fiat, otherwise the indicative fallback. Unknown fiats
     * degrade to USD. Display only (not a tradable quote), used by the public
     * marketing converter / prices page.
     *
     * @param  array<int,string>  $symbols
     * @return array<string,string>
     */
    public function ratesWithFallback(string $base, array $symbols): array
    {
        $base = strtoupper($base);
        if (! in_array($base, self::FIATS, true)) {
            $base = 'USD';
        }

        $live = $this->data()[strtolower($base)] ?? [];
        $out = [];
        foreach ($symbols as $sym) {
            if (isset($live[$sym]) && $live[$sym] > 0) {
                $out[$sym] = (string) BigDecimal::of((string) $live[$sym])->toScale(2, RoundingMode::HALF_UP);
            } else {
                $out[$sym] = $this->fallbackRate($base, $sym);
            }
        }

        return $out;
    }

    /** Offline fallback price of $sym in $base fiat (BDT keeps its bespoke table). */
    private function fallbackRate(string $base, string $sym): string
    {
        if ($base === 'BDT' && isset(self::FALLBACK_BDT[$sym])) {
            return self::FALLBACK_BDT[$sym];
        }

        $usd = self::FALLBACK_USD[$sym] ?? null;
        if ($usd === null) {
            return '0';
        }

        return (string) BigDecimal::of($usd)
            ->multipliedBy(self::FIAT_PER_USD[$base] ?? '1')
            ->toScale(2, RoundingMode::HALF_UP);
    }

    /** BDT-specific shorthand kept for backward compatibility. */
    public function bdtRatesWithFallback(array $symbols): array
    {
        return $this->ratesWithFallback('BDT', $symbols);
    }

    /**
     * ['usd' => [SYM => price], 'bdt' => [SYM => price]] from CoinGecko, or []
     * on failure. Only successful fetches are cached (so an outage retries soon).
     *
     * @return array{usd?:array<string,mixed>,bdt?:array<string,mixed>,eur?:array<string,mixed>}
     */
    private function data(): array
    {
        $key = 'rates:coingecko:simple';
        $cached = Cache::get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $fresh = $this->fetch();
        if ($fresh !== []) {
            $ttl = (int) config('providers.rates.cache_ttl', 60);
            Cache::put($key, $fresh, $ttl > 0 ? $ttl : 60);
        }

        return $fresh;
    }

    /** @return array{usd?:array<string,mixed>,bdt?:array<string,mixed>} */
    private function fetch(): array
    {
        try {
            $resp = Http::timeout(4)->acceptJson()->get(self::ENDPOINT, [
                'ids' => implode(',', array_values(self::IDS)),
                'vs_currencies' => 'usd,bdt,eur',
            ]);

            if (! $resp->successful()) {
                return [];
            }

            $body = $resp->json();
            if (! is_array($body)) {
                return [];
            }

            $usd = [];
            $bdt = [];
            $eur = [];
            foreach (self::IDS as $sym => $id) {
                if (isset($body[$id]['usd'])) {
                    $usd[$sym] = $body[$id]['usd'];
                }
                if (isset($body[$id]['bdt'])) {
                    $bdt[$sym] = $body[$id]['bdt'];
                }
                if (isset($body[$id]['eur'])) {
                    $eur[$sym] = $body[$id]['eur'];
                }
            }

            // Derive fiat cross-prices (USD per 1 fiat) so rate() can price fiat pairs.
            if (isset($body['tether']['usd'], $body['tether']['bdt']) && $body['tether']['bdt'] > 0) {
                $usd['USD'] = 1.0;
                $usd['BDT'] = $body['tether']['usd'] / $body['tether']['bdt'];
            }
            if (isset($body['tether']['usd'], $body['tether']['eur']) && $body['tether']['eur'] > 0) {
                $usd['EUR'] = $body['tether']['usd'] / $body['tether']['eur'];
            }

            return $usd === [] ? [] : ['usd' => $usd, 'bdt' => $bdt, 'eur' => $eur];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
