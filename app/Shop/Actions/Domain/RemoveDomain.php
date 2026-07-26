<?php

declare(strict_types=1);

namespace App\Shop\Actions\Domain;

use App\Shop\Events\DomainRemoved;
use App\Shop\Models\Domain;
use App\Shop\Services\Domain\DomainResolver;

/**
 * Disconnect a custom domain. Drops the routing cache first (so the host stops
 * resolving immediately), deletes the row, and fires {@see DomainRemoved}. The
 * host frees up for reuse once removed.
 */
class RemoveDomain
{
    public function __construct(private readonly DomainResolver $resolver) {}

    public function execute(Domain $domain): void
    {
        $host = $domain->host;

        $this->resolver->forget($domain);
        $domain->delete();

        DomainRemoved::dispatch($domain, $host);
    }
}
