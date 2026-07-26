<?php

declare(strict_types=1);

namespace App\Shop\Actions\Domain;

use App\Shop\Enums\DomainStatus;
use App\Shop\Jobs\VerifyDomainJob;
use App\Shop\Models\Domain;

/**
 * Re-run verification on demand ("Verify again" / operator re-verify). Resets the
 * attempt counter so a fixed DNS setup gets a fresh set of retries, moves the
 * domain to Verifying, and queues the check.
 */
class ReverifyDomain
{
    public function execute(Domain $domain): Domain
    {
        $domain->forceFill([
            'status' => DomainStatus::Verifying,
            'verify_attempts' => 0,
            'last_error' => null,
        ])->save();

        VerifyDomainJob::dispatch($domain->getKey());

        return $domain;
    }
}
