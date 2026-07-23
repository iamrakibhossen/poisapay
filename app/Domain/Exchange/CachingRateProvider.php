<?php

declare(strict_types=1);

namespace App\Domain\Exchange;

use App\Domain\Exchange\Contracts\RateProvider;
use App\Models\Asset;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Cache;

/**
 * Caching decorator around any {@see RateProvider}. Live feeds are rate-limited
 * and occasionally slow; this memoises each pair for a short TTL so hot paths
 * (card JIT, conversions) don't hammer the upstream. Wraps the configured driver
 * transparently — the stub or a real feed behave identically to callers.
 */
final class CachingRateProvider implements RateProvider
{
    public function __construct(
        private readonly RateProvider $inner,
        private readonly int $ttlSeconds = 60,
    ) {}

    public function rate(Asset $from, Asset $to): BigDecimal
    {
        if ($this->ttlSeconds <= 0) {
            return $this->inner->rate($from, $to);
        }

        $key = "rate:{$from->symbol}:{$to->symbol}";

        $value = Cache::remember(
            $key,
            $this->ttlSeconds,
            fn (): string => (string) $this->inner->rate($from, $to),
        );

        return BigDecimal::of($value);
    }
}
