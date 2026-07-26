<?php

declare(strict_types=1);

namespace App\Shop\Events;

use App\Shop\Models\Domain;
use Illuminate\Database\Eloquent\Model;

/** A custom domain passed DNS ownership + routing verification. */
class DomainVerified extends ShopDomainEvent
{
    public function __construct(public readonly Domain $domain) {}

    public function auditAction(): string
    {
        return 'domain.verified';
    }

    public function auditSubject(): ?Model
    {
        return $this->domain;
    }

    public function auditData(): array
    {
        return ['host' => $this->domain->host, 'record' => $this->domain->dns_record_type?->value];
    }
}
