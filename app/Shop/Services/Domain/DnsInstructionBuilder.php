<?php

declare(strict_types=1);

namespace App\Shop\Services\Domain;

use App\Shop\Models\Domain;

/**
 * Builds the DNS records a merchant must add to point a domain at the platform:
 * a TXT ownership challenge plus a CNAME to the platform target. CNAME-only by
 * design — a subdomain (shop.brand.com) is recommended; apex/root domains work
 * only where the DNS provider supports CNAME-flattening / ALIAS. Each row is
 * copy-pasteable (Host / Type / Value / TTL).
 */
class DnsInstructionBuilder
{
    /**
     * @return array{recommended: string, records: list<array{label: string, type: string, host: string, value: string, ttl: int, recommended: bool}>}
     */
    public function for(Domain $domain): array
    {
        $cfg = config('shop.custom_domains', []);
        $ttl = (int) ($cfg['dns_ttl'] ?? 3600);
        $host = $domain->host;

        $records = [
            [
                'label' => 'Ownership',
                'type' => 'TXT',
                'host' => trim(($cfg['txt_name'] ?? '_poisapay-challenge').'.'.$host, '.'),
                'value' => ($cfg['txt_prefix'] ?? 'poisapay-domain-verification=').$domain->verification_token,
                'ttl' => $ttl,
                'recommended' => true,
            ],
            [
                'label' => 'Routing',
                'type' => 'CNAME',
                'host' => $host,
                'value' => (string) ($cfg['cname_target'] ?? ''),
                'ttl' => $ttl,
                'recommended' => true,
            ],
        ];

        return ['recommended' => 'cname', 'records' => $records];
    }
}
