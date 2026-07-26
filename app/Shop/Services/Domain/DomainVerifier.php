<?php

declare(strict_types=1);

namespace App\Shop\Services\Domain;

use App\Shop\Contracts\DnsResolver;
use App\Shop\DTOs\DomainVerificationResult;
use App\Shop\Enums\DnsRecordType;
use App\Shop\Models\Domain;

/**
 * Verifies a custom domain against live DNS. Two independent checks, both required:
 *
 *  1. Ownership — a TXT challenge at `_poisapay-challenge.<host>` carries the
 *     domain's unique token. Proves the merchant controls the DNS zone, which
 *     blocks connecting a domain you don't own and defends against takeover.
 *  2. Routing — the host resolves to the platform via a CNAME to the platform
 *     target. Without this the domain wouldn't reach us. (CNAME-only by design;
 *     apex domains use their provider's CNAME-flattening / ALIAS.)
 */
class DomainVerifier
{
    public function __construct(private readonly DnsResolver $dns) {}

    public function verify(Domain $domain): DomainVerificationResult
    {
        $cfg = config('shop.custom_domains', []);

        $ownershipOk = $this->checkOwnership($domain, $cfg);
        [$routingOk, $recordType] = $this->checkRouting($domain->host, $cfg);

        $error = match (true) {
            ! $ownershipOk => 'Ownership TXT record not found or incorrect. Add the TXT record shown, then verify again.',
            ! $routingOk => 'Domain is not pointing to PoisaPay yet. Add the CNAME record shown, then verify again.',
            default => null,
        };

        return new DomainVerificationResult($ownershipOk, $routingOk, $recordType, $error);
    }

    /** @param array<string, mixed> $cfg */
    private function checkOwnership(Domain $domain, array $cfg): bool
    {
        $name = trim(($cfg['txt_name'] ?? '_poisapay-challenge').'.'.$domain->host, '.');
        $expected = ($cfg['txt_prefix'] ?? 'poisapay-domain-verification=').$domain->verification_token;

        foreach ($this->dns->txt($name) as $txt) {
            if (hash_equals($expected, trim($txt))) {
                return true;
            }
        }

        return false;
    }

    /**
     * CNAME-only: the host must CNAME to the platform target.
     *
     * @param  array<string, mixed>  $cfg
     * @return array{0: bool, 1: ?DnsRecordType}
     */
    private function checkRouting(string $host, array $cfg): array
    {
        $cnameTarget = strtolower(rtrim((string) ($cfg['cname_target'] ?? ''), '.'));

        if ($cnameTarget !== '' && in_array($cnameTarget, $this->dns->cname($host), true)) {
            return [true, DnsRecordType::Cname];
        }

        return [false, null];
    }
}
