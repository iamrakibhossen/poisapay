<?php

declare(strict_types=1);

namespace App\Shop\Events;

use App\Shop\Models\Domain;
use Illuminate\Database\Eloquent\Model;

/** SSL issuance failed; auto-retried with backoff until the attempt ceiling. */
class SslFailed extends ShopDomainEvent
{
    public function __construct(
        public readonly Domain $domain,
        public readonly bool $exhausted,
    ) {}

    public function auditAction(): string
    {
        return 'domain.ssl_failed';
    }

    public function auditSubject(): ?Model
    {
        return $this->domain;
    }

    public function auditData(): array
    {
        return ['host' => $this->domain->host, 'attempts' => $this->domain->ssl_attempts, 'exhausted' => $this->exhausted];
    }
}
