<?php

declare(strict_types=1);

namespace App\Shop\Enums;

/**
 * Lifecycle of a custom domain's TLS certificate.
 *
 *   Pending → awaiting verification before issuance can begin
 *   Issuing → certificate order in flight (queued)
 *   Active  → certificate installed; the domain serves over HTTPS
 *   Failed  → issuance failed; auto-retried with backoff
 */
enum DomainSslStatus: string
{
    case Pending = 'pending';
    case Issuing = 'issuing';
    case Active = 'active';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Issuing => 'Issuing',
            self::Active => 'Active',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Issuing => 'info',
            self::Pending => 'warning',
            self::Failed => 'danger',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
