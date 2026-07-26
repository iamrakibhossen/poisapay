<?php

declare(strict_types=1);

namespace App\Shop\Services\Ssl;

use App\Shop\Contracts\SslProvisioner;
use App\Shop\Models\Domain;
use RuntimeException;

/**
 * Production SSL driver placeholder. A real implementation places an ACME order
 * (HTTP-01/DNS-01) against the edge/ingress and installs the resulting cert.
 * Until that infra lands, this throws so provisioning is recorded as Failed and
 * auto-retried, rather than silently reporting HTTPS that doesn't exist.
 *
 * Select with SHOP_DOMAIN_SSL_DRIVER=acme once wired.
 */
class AcmeSslProvisioner implements SslProvisioner
{
    public function issue(Domain $domain): void
    {
        throw new RuntimeException('ACME SSL provisioning is not configured (set up the edge integration).');
    }
}
