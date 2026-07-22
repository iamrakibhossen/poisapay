<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

enum ReferralStatus: string
{
    use HasMeta;

    case Pending = 'pending';
    case Qualified = 'qualified';
    case Rewarded = 'rewarded';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Qualified => 'info',
            self::Rewarded => 'success',
        };
    }
}
