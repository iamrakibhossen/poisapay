<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

enum RampStatus: string
{
    use HasMeta;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Credited = 'credited';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'info',
            self::Credited => 'success',
            self::Failed => 'danger',
        };
    }
}
