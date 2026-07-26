<?php

declare(strict_types=1);

namespace App\Shop\Enums;

/**
 * Lifecycle of a custom domain's ownership verification.
 *
 *   Pending   → connected, DNS records not yet added/seen
 *   Verifying → a verification check is queued/running
 *   Verified  → DNS ownership + routing confirmed; the domain serves its page
 *   Failed    → last check failed (see Domain::last_error); auto-retried
 */
enum DomainStatus: string
{
    case Pending = 'pending';
    case Verifying = 'verifying';
    case Verified = 'verified';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Verifying => 'Verifying',
            self::Verified => 'Verified',
            self::Failed => 'Failed',
        };
    }

    /** UI badge colour (matches x-ui.badge palette). */
    public function color(): string
    {
        return match ($this) {
            self::Verified => 'success',
            self::Verifying => 'info',
            self::Pending => 'warning',
            self::Failed => 'danger',
        };
    }

    public function isVerified(): bool
    {
        return $this === self::Verified;
    }
}
