<?php

declare(strict_types=1);

namespace App\Shop\Actions\Domain;

use App\Shop\Enums\DomainStatus;
use App\Shop\Events\DomainVerificationFailed;
use App\Shop\Events\DomainVerified;
use App\Shop\Jobs\ProvisionSslJob;
use App\Shop\Jobs\VerifyDomainJob;
use App\Shop\Models\Domain;
use App\Shop\Services\Domain\DomainResolver;
use App\Shop\Services\Domain\DomainVerifier;
use Illuminate\Support\Facades\Date;

/**
 * Run one verification pass for a domain and advance its state machine. On
 * success it kicks off SSL provisioning and warms the routing cache; on failure
 * it records the error and (unless the attempt ceiling is hit) re-queues itself
 * with backoff. Disabled domains are skipped.
 */
class VerifyDomain
{
    public function __construct(
        private readonly DomainVerifier $verifier,
        private readonly DomainResolver $resolver,
    ) {}

    public function execute(Domain $domain): Domain
    {
        if ($domain->isDisabled()) {
            return $domain;
        }

        if ($domain->status !== DomainStatus::Verified) {
            $domain->forceFill(['status' => DomainStatus::Verifying])->save();
        }

        $result = $this->verifier->verify($domain);

        $domain->forceFill([
            'last_checked_at' => Date::now(),
            'verify_attempts' => $domain->verify_attempts + 1,
        ]);

        if ($result->verified()) {
            $domain->forceFill([
                'status' => DomainStatus::Verified,
                'dns_record_type' => $result->recordType,
                'verified_at' => $domain->verified_at ?? Date::now(),
                'last_error' => null,
            ])->save();

            DomainVerified::dispatch($domain);
            $this->resolver->warm($domain);
            ProvisionSslJob::dispatch($domain->getKey());

            return $domain;
        }

        $max = (int) config('shop.custom_domains.verify_max_attempts', 10);
        $exhausted = $domain->verify_attempts >= $max;

        $domain->forceFill([
            'status' => DomainStatus::Failed,
            'last_error' => $result->error,
        ])->save();

        DomainVerificationFailed::dispatch($domain, $exhausted);

        if (! $exhausted) {
            VerifyDomainJob::dispatch($domain->getKey())
                ->delay(Date::now()->addSeconds((int) config('shop.custom_domains.verify_backoff', 120)));
        }

        return $domain;
    }
}
