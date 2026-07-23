<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/**
 * Fund-custody state of the escrow record (distinct from the order's lifecycle).
 * Locked → the seller's USDT sits in user:p2p_escrow; Released → paid to the
 * buyer; Refunded → returned to the seller. This mirrors card_authorizations,
 * linking the lock/release journal entries for audit.
 */
enum P2pEscrowStatus: string
{
    use HasMeta;

    case Locked = 'locked';
    case Released = 'released';
    case Refunded = 'refunded';

    public function isSettled(): bool
    {
        return $this !== self::Locked;
    }

    public function color(): string
    {
        return match ($this) {
            self::Locked => 'warning',
            self::Released => 'success',
            self::Refunded => 'muted',
        };
    }
}
