<?php

declare(strict_types=1);

namespace App\Shop\Contracts;

/**
 * DNS lookups for domain verification. Abstracted behind a contract so tests can
 * swap in a deterministic fake instead of hitting real resolvers.
 */
interface DnsResolver
{
    /** @return list<string> lowercased CNAME targets for the host (trailing dot stripped). */
    public function cname(string $host): array;

    /** @return list<string> A-record IPv4 addresses for the host. */
    public function a(string $host): array;

    /** @return list<string> TXT record strings for the host. */
    public function txt(string $host): array;
}
