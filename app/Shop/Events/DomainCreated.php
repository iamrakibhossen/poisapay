<?php

declare(strict_types=1);

namespace App\Shop\Events;

use App\Shop\Models\Domain;
use Illuminate\Database\Eloquent\Model;

/** A merchant connected a custom domain (verification not yet started). */
class DomainCreated extends ShopDomainEvent
{
    public function __construct(public readonly Domain $domain) {}

    public function auditAction(): string
    {
        return 'domain.created';
    }

    public function auditSubject(): ?Model
    {
        return $this->domain;
    }

    public function auditData(): array
    {
        return ['host' => $this->domain->host, 'sales_page_id' => $this->domain->sales_page_id];
    }
}
