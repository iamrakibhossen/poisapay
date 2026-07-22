<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Deposit lifecycle (TDD §6.1). */
enum DepositStatus: string
{
    use HasMeta;

    case Detected = 'detected';
    case Confirming = 'confirming';
    case Credited = 'credited';
    case Orphaned = 'orphaned';

    public function color(): string
    {
        return match ($this) {
            self::Detected => 'gray',
            self::Confirming => 'warning',
            self::Credited => 'success',
            self::Orphaned => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Credited || $this === self::Orphaned;
    }
}
