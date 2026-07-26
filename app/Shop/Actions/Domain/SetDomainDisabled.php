<?php

declare(strict_types=1);

namespace App\Shop\Actions\Domain;

use App\Shop\Jobs\VerifyDomainJob;
use App\Shop\Models\Domain;
use App\Shop\Services\Domain\DomainResolver;
use App\Shop\Support\ShopAudit;
use Illuminate\Support\Facades\Date;

/**
 * Operator kill-switch. Disabling stops the domain resolving (drops the cache);
 * re-enabling clears the flag and re-queues verification so serving only resumes
 * once DNS still checks out. Audited under `shop.domain.*`.
 */
class SetDomainDisabled
{
    public function __construct(private readonly DomainResolver $resolver) {}

    public function execute(Domain $domain, bool $disabled): Domain
    {
        $domain->forceFill(['disabled_at' => $disabled ? Date::now() : null])->save();
        $this->resolver->forget($domain);

        ShopAudit::log($disabled ? 'domain.disabled' : 'domain.enabled', $domain, ['host' => $domain->host]);

        if (! $disabled) {
            VerifyDomainJob::dispatch($domain->getKey());
        }

        return $domain;
    }
}
