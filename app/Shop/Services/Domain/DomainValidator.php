<?php

declare(strict_types=1);

namespace App\Shop\Services\Domain;

use App\Shop\Exceptions\DomainException;
use App\Shop\Models\Domain;
use App\Shop\Support\DomainName;
use App\Shop\Support\PlatformHost;

/**
 * Gatekeeps which hosts may be connected. Operates on an already-normalized host
 * and throws a merchant-facing {@see DomainException} on the first failure:
 * invalid format, platform-owned, reserved/internal, or already taken.
 */
class DomainValidator
{
    /** @throws DomainException */
    public function validate(string $normalizedHost, ?string $ignoreDomainId = null): void
    {
        if (! DomainName::isValidFormat($normalizedHost)) {
            throw DomainException::invalidFormat($normalizedHost);
        }

        if (PlatformHost::is($normalizedHost)) {
            throw DomainException::platformDomain($normalizedHost);
        }

        if ($this->isReserved($normalizedHost)) {
            throw DomainException::reserved($normalizedHost);
        }

        if ($this->isTaken($normalizedHost, $ignoreDomainId)) {
            throw DomainException::duplicate($normalizedHost);
        }
    }

    private function isReserved(string $host): bool
    {
        $cfg = config('shop.custom_domains', []);

        if (in_array($host, array_map('strtolower', (array) ($cfg['reserved_hosts'] ?? [])), true)) {
            return true;
        }

        foreach ((array) ($cfg['reserved_suffixes'] ?? []) as $suffix) {
            $suffix = strtolower((string) $suffix);
            if ($suffix !== '' && str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    private function isTaken(string $host, ?string $ignoreDomainId): bool
    {
        return Domain::query()
            ->where('host', $host)
            ->when($ignoreDomainId !== null, fn ($q) => $q->whereKeyNot($ignoreDomainId))
            ->exists();
    }
}
