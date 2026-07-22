<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Domain\Audit\ActivityLogger;
use App\Models\Card;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Set / change a card PIN. The PIN is one-way hashed (bcrypt) and never stored,
 * logged, or transmitted in the clear — the plaintext leaves this method's scope
 * immediately. In production the hash would be forwarded to the issuer's HSM.
 */
class SetCardPinAction
{
    public function execute(Card $card, string $pin): Card
    {
        if (! preg_match('/^\d{4,6}$/', $pin)) {
            throw new RuntimeException('PIN must be 4 to 6 digits.');
        }

        $card->update(['pin_hash' => Hash::make($pin)]);
        ActivityLogger::log('card.pin.set', $card);

        return $card->refresh();
    }
}
