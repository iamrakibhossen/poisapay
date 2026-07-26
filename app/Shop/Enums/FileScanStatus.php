<?php

declare(strict_types=1);

namespace App\Shop\Enums;

/**
 * Malware-scan lifecycle of a downloadable product file. A freshly uploaded file
 * is `Pending` and NOT deliverable until the queued scan clears it to `Clean`;
 * `Infected` files are quarantined (never served, dropped as the current version).
 */
enum FileScanStatus: string
{
    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Scanning',
            self::Clean => 'Ready',
            self::Infected => 'Blocked',
        };
    }

    /** Badge accent for the seller UI (matches the x-ui.badge palette). */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Clean => 'success',
            self::Infected => 'danger',
        };
    }

    /** Only a clean file may be delivered to a buyer. */
    public function isDeliverable(): bool
    {
        return $this === self::Clean;
    }
}
