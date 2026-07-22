<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

enum SweepStatus: string
{
    use HasMeta;

    case Pending = 'pending';
    case Gassing = 'gassing';
    case Signing = 'signing';
    case Broadcast = 'broadcast';
    case Swept = 'swept';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Swept => 'success',
            self::Failed => 'danger',
            default => 'warning',
        };
    }
}
