<?php

declare(strict_types=1);

namespace App\Shop\Events;

use App\Shop\Models\Domain;
use Illuminate\Database\Eloquent\Model;

/** A TLS certificate was issued/installed for a custom domain. */
class SslIssued extends ShopDomainEvent
{
    public function __construct(public readonly Domain $domain) {}

    public function auditAction(): string
    {
        return 'domain.ssl_issued';
    }

    public function auditSubject(): ?Model
    {
        return $this->domain;
    }

    public function auditData(): array
    {
        return ['host' => $this->domain->host];
    }
}
