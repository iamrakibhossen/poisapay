<?php

declare(strict_types=1);

namespace App\Shop\Events;

use App\Shop\Models\Domain;
use Illuminate\Database\Eloquent\Model;

/** A verification pass failed; the domain is auto-retried until the attempt ceiling. */
class DomainVerificationFailed extends ShopDomainEvent
{
    public function __construct(
        public readonly Domain $domain,
        public readonly bool $exhausted,
    ) {}

    public function auditAction(): string
    {
        return 'domain.verification_failed';
    }

    public function auditSubject(): ?Model
    {
        return $this->domain;
    }

    public function auditData(): array
    {
        return [
            'host' => $this->domain->host,
            'error' => $this->domain->last_error,
            'attempts' => $this->domain->verify_attempts,
            'exhausted' => $this->exhausted,
        ];
    }
}
