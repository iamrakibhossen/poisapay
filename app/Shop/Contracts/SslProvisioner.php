<?php

declare(strict_types=1);

namespace App\Shop\Contracts;

use App\Shop\Models\Domain;

/**
 * Provider-agnostic TLS certificate issuance for a verified custom domain.
 * The simulated driver marks certs active immediately; a real driver (ACME/edge)
 * performs an HTTP-01/DNS-01 order. Implementations must be idempotent and throw
 * on failure so the caller can record the error and schedule a retry.
 */
interface SslProvisioner
{
    /** Issue/renew the certificate for the domain. Throws on failure. */
    public function issue(Domain $domain): void;
}
