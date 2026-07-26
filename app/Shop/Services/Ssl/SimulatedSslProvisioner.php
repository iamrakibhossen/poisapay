<?php

declare(strict_types=1);

namespace App\Shop\Services\Ssl;

use App\Shop\Contracts\SslProvisioner;
use App\Shop\Models\Domain;

/**
 * Default SSL driver: marks a certificate active without contacting a CA. Used in
 * dev/test and until the real ACME/edge integration is wired. A verified domain
 * is a precondition (enforced by the caller), so issuance is always safe here.
 */
class SimulatedSslProvisioner implements SslProvisioner
{
    public function issue(Domain $domain): void
    {
        // No-op: the ProvisionSsl action flips ssl_status to Active on return.
    }
}
