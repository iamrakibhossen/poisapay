<?php

declare(strict_types=1);

namespace App\Card\Support;

use App\Card\DTOs\CardholderData;
use App\Models\User;

/** Maps our User onto the provider-neutral CardholderData. */
class CardholderMapper
{
    public static function fromUser(User $user): CardholderData
    {
        $name = trim((string) ($user->getAttribute('name') ?? ''));
        $parts = preg_split('/\s+/', $name, 2) ?: [];
        $first = $parts[0] ?? '';
        $last = $parts[1] ?? '';

        return new CardholderData(
            externalId: (string) $user->getKey(),
            firstName: $first !== '' ? $first : 'Card',
            lastName: $last !== '' ? $last : 'Holder',
            email: $user->getAttribute('email'),
            phone: $user->getAttribute('phone'),
        );
    }
}
