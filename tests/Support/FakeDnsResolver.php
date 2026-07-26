<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shop\Contracts\DnsResolver;

/**
 * Deterministic DNS resolver for tests. Records are seeded per host+type; any
 * unseeded lookup returns [] (mirrors NXDOMAIN / "no record" in the real driver).
 */
class FakeDnsResolver implements DnsResolver
{
    /** @var array<string, list<string>> */
    private array $cname = [];

    /** @var array<string, list<string>> */
    private array $a = [];

    /** @var array<string, list<string>> */
    private array $txt = [];

    /** @param list<string> $targets */
    public function setCname(string $host, array $targets): self
    {
        $this->cname[$host] = array_map('strtolower', $targets);

        return $this;
    }

    /** @param list<string> $ips */
    public function setA(string $host, array $ips): self
    {
        $this->a[$host] = $ips;

        return $this;
    }

    /** @param list<string> $values */
    public function setTxt(string $host, array $values): self
    {
        $this->txt[$host] = $values;

        return $this;
    }

    public function cname(string $host): array
    {
        return $this->cname[$host] ?? [];
    }

    public function a(string $host): array
    {
        return $this->a[$host] ?? [];
    }

    public function txt(string $host): array
    {
        return $this->txt[$host] ?? [];
    }
}
