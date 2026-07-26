<?php

declare(strict_types=1);

namespace App\Shop\Services\Domain;

use App\Shop\Enums\SalesPageStatus;
use App\Shop\Models\Domain;
use App\Shop\Support\DomainName;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves an incoming request host to the sales page it serves — the routing
 * hot path. Cached (positive *and* negative) so a warm lookup does zero DB work
 * and unknown Host headers can't flood the DB. The cache is keyed by the
 * normalized apex host, so `example.com` and `www.example.com` share one entry.
 *
 * Invalidation is automatic: any Domain write forgets its key (ShopServiceProvider).
 */
class DomainResolver
{
    private const PREFIX = 'shop:domain:';

    public function cacheKey(string $host): string
    {
        return self::PREFIX.$host;
    }

    /**
     * The serviceable mapping for a host, or null if none.
     *
     * @return array{host: string, slug: string, ssl: bool}|null
     */
    public function resolve(string $host): ?array
    {
        $host = DomainName::normalize($host);
        if ($host === '') {
            return null;
        }

        $key = $this->cacheKey($host);
        $cached = Cache::get($key);
        if ($cached !== null) {
            // `false` is the cached "no such domain" sentinel.
            return $cached === false ? null : $cached;
        }

        $result = $this->lookup($host);
        Cache::put($key, $result ?? false, (int) config('shop.custom_domains.cache_ttl', 3600));

        return $result;
    }

    /** @return array{host: string, slug: string, ssl: bool}|null */
    private function lookup(string $host): ?array
    {
        $domain = Domain::query()
            ->serviceable()
            ->where('host', $host)
            ->with('salesPage:id,slug,status')
            ->first();

        if ($domain === null
            || $domain->salesPage === null
            || $domain->salesPage->status !== SalesPageStatus::Published) {
            return null;
        }

        return ['host' => $domain->host, 'slug' => $domain->salesPage->slug, 'ssl' => $domain->isSslActive()];
    }

    public function forget(Domain $domain): void
    {
        Cache::forget($this->cacheKey($domain->host));
        Cache::forget($this->cacheKey($domain->apexHost()));
    }

    /** Prime the cache after a status change so the first request is warm. */
    public function warm(Domain $domain): void
    {
        $this->forget($domain);
        $this->resolve($domain->host);
    }
}
