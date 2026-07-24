<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caching gateway for every analytics report. Expensive aggregate queries run at
 * most once per {@see self::TTL} per (report, window) — the dashboard reads from
 * Redis, never re-scanning the ledger on each request.
 *
 * Deliberately tag-free so it works identically on the redis, database and array
 * (test) stores: invalidation is a monotonic version counter folded into every
 * key, so {@see flush()} makes all previously cached reports unreachable at once
 * (e.g. after the nightly rollup rewrites the summary tables).
 */
class AnalyticsCache
{
    /** Minimum cache lifetime mandated for analytics endpoints: 1 hour. */
    public const TTL = 3600;

    private const VERSION_KEY = 'analytics:version';

    /**
     * @template T
     *
     * @param  Closure():T  $callback
     * @return T
     */
    public function remember(string $report, Period $period, Closure $callback, ?int $ttl = null): mixed
    {
        return Cache::remember($this->key($report, $period), $ttl ?? self::TTL, $callback);
    }

    public function key(string $report, Period $period): string
    {
        return "analytics:{$this->version()}:{$report}:{$period->signature()}";
    }

    public function forget(string $report, Period $period): void
    {
        Cache::forget($this->key($report, $period));
    }

    /** Invalidate every cached report by rotating the version prefix. */
    public function flush(): void
    {
        Cache::increment(self::VERSION_KEY);
    }

    private function version(): int
    {
        return (int) Cache::rememberForever(self::VERSION_KEY, fn () => 1);
    }
}
