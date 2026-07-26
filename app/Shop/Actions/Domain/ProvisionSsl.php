<?php

declare(strict_types=1);

namespace App\Shop\Actions\Domain;

use App\Shop\Contracts\SslProvisioner;
use App\Shop\Enums\DomainSslStatus;
use App\Shop\Enums\DomainStatus;
use App\Shop\Events\SslFailed;
use App\Shop\Events\SslIssued;
use App\Shop\Jobs\ProvisionSslJob;
use App\Shop\Models\Domain;
use App\Shop\Services\Domain\DomainResolver;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * Provision (or renew) the TLS certificate for a verified domain. Idempotent and
 * safe to retry; on failure it records the error and re-queues with backoff until
 * the attempt ceiling. Never issues for an unverified/disabled domain — a cert for
 * a domain we don't control would be a takeover vector.
 */
class ProvisionSsl
{
    public function __construct(
        private readonly SslProvisioner $provisioner,
        private readonly DomainResolver $resolver,
    ) {}

    public function execute(Domain $domain): Domain
    {
        if ($domain->status !== DomainStatus::Verified || $domain->isDisabled()) {
            return $domain;
        }

        if ($domain->ssl_status === DomainSslStatus::Active) {
            return $domain;
        }

        $domain->forceFill(['ssl_status' => DomainSslStatus::Issuing])->save();

        try {
            $this->provisioner->issue($domain);
        } catch (Throwable $e) {
            $max = (int) config('shop.custom_domains.ssl.max_attempts', 5);
            $exhausted = ($domain->ssl_attempts + 1) >= $max;

            $domain->forceFill([
                'ssl_status' => DomainSslStatus::Failed,
                'ssl_attempts' => $domain->ssl_attempts + 1,
                'last_error' => 'SSL: '.$e->getMessage(),
            ])->save();

            SslFailed::dispatch($domain, $exhausted);

            if (! $exhausted) {
                ProvisionSslJob::dispatch($domain->getKey())
                    ->delay(Date::now()->addSeconds((int) config('shop.custom_domains.ssl.backoff', 300)));
            }

            return $domain;
        }

        $domain->forceFill([
            'ssl_status' => DomainSslStatus::Active,
            'ssl_attempts' => $domain->ssl_attempts + 1,
        ])->save();

        SslIssued::dispatch($domain);
        $this->resolver->warm($domain);

        return $domain;
    }
}
