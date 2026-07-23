<?php

declare(strict_types=1);

namespace App\Domain\Security\Reputation;

use App\Domain\Security\Contracts\IpReputationProvider;
use App\Domain\Security\DTO\IpReputation;

/**
 * Default IP reputation provider. Makes no external calls: it screens the address
 * against an admin-configurable denylist (exact IPs) so operators can block known
 * bad actors immediately, and returns clean for everything else. A real vendor
 * replaces this without any call-site change.
 */
final class StubIpReputationProvider implements IpReputationProvider
{
    public function name(): string
    {
        return 'stub';
    }

    public function check(string $ip): IpReputation
    {
        $denylist = (array) getSetting('security_ip_denylist', []);

        if (in_array($ip, $denylist, true)) {
            return new IpReputation($ip, riskScore: 100, raw: ['list' => 'admin_denylist']);
        }

        return IpReputation::clean($ip);
    }
}
