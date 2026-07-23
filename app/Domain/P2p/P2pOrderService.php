<?php

declare(strict_types=1);

namespace App\Domain\P2p;

use App\Enums\P2pOrderStatus;
use App\Models\P2pAd;
use App\Models\P2pOrder;
use App\Models\P2pOrderEvent;
use App\Support\Money;
use Brick\Math\BigInteger;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Shared P2P order helpers: guarded state transitions (with an append-only
 * timeline entry), order-ref generation, and ad-inventory restoration.
 */
class P2pOrderService
{
    /** Apply a guarded status transition and record it on the timeline. */
    public function transition(
        P2pOrder $order,
        P2pOrderStatus $to,
        array $attributes = [],
        string $actorType = 'system',
        ?string $actorId = null,
        ?string $note = null,
    ): void {
        $from = $order->status;

        if ($from !== $to && ! $from->canTransitionTo($to)) {
            throw new RuntimeException("Illegal P2P order transition {$from->value} → {$to->value}.");
        }

        $order->forceFill(array_merge($attributes, ['status' => $to]))->save();
        $this->recordEvent($order, $from->value, $to->value, $actorType, $actorId, $note);
    }

    public function recordEvent(
        P2pOrder $order,
        ?string $from,
        string $to,
        string $actorType = 'system',
        ?string $actorId = null,
        ?string $note = null,
    ): void {
        P2pOrderEvent::create([
            'order_id' => $order->id,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }

    public function generateRef(): string
    {
        do {
            $ref = 'P2P'.strtoupper(Str::random(9));
        } while (P2pOrder::where('ref', $ref)->exists());

        return $ref;
    }

    /** Return reserved crypto to an ad's available inventory, under a row lock. */
    public function restoreAdAvailability(string $adId, Money $amount): void
    {
        $ad = P2pAd::where('id', $adId)->lockForUpdate()->first();
        if (! $ad) {
            return;
        }

        $new = BigInteger::of((string) $ad->available_amount)->plus($amount->base);
        $ad->update(['available_amount' => (string) $new]);
    }
}
