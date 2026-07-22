<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Revenue-withdrawal lifecycle (Pending → Approved → Broadcast → Processing → Completed / Failed). */
enum RevenueWithdrawalStatus: string
{
    use HasMeta;

    case Pending = 'pending';
    case Approved = 'approved';
    case Broadcasting = 'broadcasting';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Approved, self::Broadcasting, self::Processing], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved, self::Broadcasting, self::Processing => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
