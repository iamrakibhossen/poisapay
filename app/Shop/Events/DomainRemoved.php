<?php

declare(strict_types=1);

namespace App\Shop\Events;

use App\Shop\Models\Domain;
use Illuminate\Database\Eloquent\Model;

/** A custom domain was disconnected (by the merchant or an operator). */
class DomainRemoved extends ShopDomainEvent
{
    public function __construct(
        public readonly Domain $domain,
        public readonly string $host,
    ) {}

    public function auditAction(): string
    {
        return 'domain.removed';
    }

    public function auditSubject(): ?Model
    {
        return $this->domain;
    }

    public function auditData(): array
    {
        return ['host' => $this->host];
    }
}
