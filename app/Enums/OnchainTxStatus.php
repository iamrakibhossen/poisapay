<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

enum OnchainTxStatus: string
{
    use HasMeta;

    case Detected = 'detected';
    case Confirming = 'confirming';
    case Confirmed = 'confirmed';
    case Orphaned = 'orphaned';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Orphaned, self::Failed => 'danger',
            default => 'warning',
        };
    }
}
