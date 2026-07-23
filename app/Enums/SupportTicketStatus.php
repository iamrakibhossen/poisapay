<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Support ticket lifecycle (Wave 6). */
enum SupportTicketStatus: string
{
    use HasMeta;

    case Open = 'open';         // awaiting a staff reply
    case Pending = 'pending';   // awaiting the user's reply
    case Resolved = 'resolved'; // staff considers it done
    case Closed = 'closed';     // archived / no further replies

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Open => 'info',
            self::Pending => 'warning',
            self::Resolved => 'success',
            self::Closed => 'gray',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Open || $this === self::Pending;
    }
}
