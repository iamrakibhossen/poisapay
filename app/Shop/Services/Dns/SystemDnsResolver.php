<?php

declare(strict_types=1);

namespace App\Shop\Services\Dns;

use App\Shop\Contracts\DnsResolver;

/**
 * Real DNS resolver backed by PHP's {@see dns_get_record}. Failures (NXDOMAIN,
 * timeouts) surface as empty arrays — the verifier treats "no record" and "wrong
 * record" identically, so callers never need to distinguish resolver errors.
 */
class SystemDnsResolver implements DnsResolver
{
    public function cname(string $host): array
    {
        return array_map(
            static fn (array $r): string => rtrim(strtolower((string) ($r['target'] ?? '')), '.'),
            $this->records($host, DNS_CNAME),
        );
    }

    public function a(string $host): array
    {
        return array_values(array_filter(array_map(
            static fn (array $r): string => (string) ($r['ip'] ?? ''),
            $this->records($host, DNS_A),
        )));
    }

    public function txt(string $host): array
    {
        return array_values(array_filter(array_map(
            static fn (array $r): string => (string) ($r['txt'] ?? ''),
            $this->records($host, DNS_TXT),
        )));
    }

    /** @return list<array<string, mixed>> */
    private function records(string $host, int $type): array
    {
        // dns_get_record emits warnings on lookup failure; swallow to a clean [].
        $records = @dns_get_record($host, $type);

        return is_array($records) ? $records : [];
    }
}
