<?php

declare(strict_types=1);

namespace App\Domain\Security\Contracts;

use App\Domain\Security\DTO\IpReputation;

/**
 * IP reputation provider (proxy / Tor / VPN / abuse scoring). The stub screens
 * against an admin denylist; a real vendor (IPQualityScore, MaxMind, AbuseIPDB)
 * drops in by implementing this and registering a driver in config/providers.php.
 */
interface IpReputationProvider
{
    /** Stable provider identifier. */
    public function name(): string;

    public function check(string $ip): IpReputation;
}
