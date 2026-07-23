<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/**
 * The authoritative P2P order state machine (see the escrow-flow docs). The
 * escrow custody record follows this — funds are locked on entry to
 * WaitingPayment and only leave on a terminal transition. Guards live in
 * {@see self::canTransitionTo()} and are enforced inside each Action under a
 * row lock, so an out-of-order transition can never move money.
 */
enum P2pOrderStatus: string
{
    use HasMeta;

    case WaitingPayment = 'waiting_payment';   // seller USDT locked in escrow
    case BuyerPaid = 'buyer_paid';             // buyer attests fiat sent
    case Releasing = 'releasing';              // seller confirming receipt
    case Completed = 'completed';              // escrow released to buyer
    case Cancelled = 'cancelled';              // cancelled before payment; seller refunded
    case Expired = 'expired';                  // payment window elapsed; seller refunded
    case Disputed = 'disputed';                // awaiting operator ruling
    case Refunded = 'refunded';                // post-completion refund (admin, rare)
    case ForceReleased = 'force_released';     // admin ruled for buyer
    case ForceCancelled = 'force_cancelled';   // admin ruled for seller

    /** @return array<int, self> */
    public function nextStates(): array
    {
        return match ($this) {
            self::WaitingPayment => [self::BuyerPaid, self::Cancelled, self::Expired],
            self::BuyerPaid => [self::Releasing, self::Disputed],
            self::Releasing => [self::Completed, self::Disputed],
            self::Disputed => [self::ForceReleased, self::ForceCancelled],
            self::Completed => [self::Refunded],
            default => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->nextStates(), true);
    }

    /** Escrow still holds the seller's funds. */
    public function isOpen(): bool
    {
        return in_array($this, [self::WaitingPayment, self::BuyerPaid, self::Releasing, self::Disputed], true);
    }

    public function isFinal(): bool
    {
        return ! $this->isOpen();
    }

    /** Terminal states where the buyer received the crypto. */
    public function isSuccessful(): bool
    {
        return in_array($this, [self::Completed, self::ForceReleased], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Completed, self::ForceReleased => 'success',
            self::WaitingPayment, self::Releasing => 'warning',
            self::BuyerPaid => 'info',
            self::Disputed => 'danger',
            self::Cancelled, self::Expired, self::ForceCancelled, self::Refunded => 'muted',
        };
    }
}
