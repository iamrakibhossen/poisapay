<?php

declare(strict_types=1);

namespace App\Shop\Enums;

/**
 * DNS record kind used to point a custom domain at the platform. CNAME-only:
 * every domain CNAMEs to the platform target (apex via CNAME-flattening / ALIAS).
 */
enum DnsRecordType: string
{
    case Cname = 'cname';

    public function label(): string
    {
        return match ($this) {
            self::Cname => 'CNAME',
        };
    }
}
