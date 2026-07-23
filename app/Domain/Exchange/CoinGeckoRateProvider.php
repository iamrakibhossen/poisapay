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

    /** Indicative BDT prices used when the live feed is unavailable. */
    public const FALLBACK_BDT = [
        'USDT' => '121.50',
        'USDC' => '121.45',
        'ETH' => '402150',
        'BTC' => '7250000',
        'BNB' => '52000',
        'TON' => '385',
    ];

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
     * symbol => string BDT rate for the given crypto symbols — live when the
     * feed is available, otherwise the indicative FALLBACK_BDT value. Used by
     * the public marketing converter (display only, not a tradable quote).
     *
     * @param  array<int,string>  $symbols
     * @return array<string,string>
     */
    public function bdtRatesWithFallback(array $symbols): array
    {
        $bdt = $this->data()['bdt'] ?? [];
        $out = [];
        foreach ($symbols as $sym) {
            if (isset($bdt[$sym]) && $bdt[$sym] > 0) {
                $out[$sym] = (string) BigDecimal::of((string) $bdt[$sym])->toScale(2, RoundingMode::HALF_UP);
            } else {
                $out[$sym] = self::FALLBACK_BDT[$sym] ?? '0';
            }
        }

        return $out;
    }

    /**
     * ['usd' => [SYM => price], 'bdt' => [SYM => price]] from CoinGecko, or []
     * on failure. Only successful fetches are cached (so an outage retries soon).
     *
     * @return array{usd?:array<string,mixed>,bdt?:array<string,mixed>}
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
                'vs_currencies' => 'usd,bdt',
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
            foreach (self::IDS as $sym => $id) {
                if (isset($body[$id]['usd'])) {
                    $usd[$sym] = $body[$id]['usd'];
                }
                if (isset($body[$id]['bdt'])) {
                    $bdt[$sym] = $body[$id]['bdt'];
                }
            }

            // Derive fiat cross-prices so rate() can also price USD/BDT pairs.
            if (isset($body['tether']['usd'], $body['tether']['bdt']) && $body['tether']['bdt'] > 0) {
                $usd['USD'] = 1.0;
                $usd['BDT'] = $body['tether']['usd'] / $body['tether']['bdt']; // USD per 1 BDT
            }

            return $usd === [] ? [] : ['usd' => $usd, 'bdt' => $bdt];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
