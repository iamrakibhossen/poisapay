<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

enum TransferStatus: string
{
    use HasMeta;

    case Pending = 'pending';
    case Claimable = 'claimable';
    case Completed = 'completed';
    case Reversed = 'reversed';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Claimable => 'info',
            self::Completed => 'success',
            self::Reversed => 'gray',
        };
    }
}
