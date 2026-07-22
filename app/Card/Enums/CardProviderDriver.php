<?php

declare(strict_types=1);

namespace App\Card\Enums;

use App\Enums\Concerns\HasMeta;

/** Known card provider drivers. Value === the key in config/card.php `providers`. */
enum CardProviderDriver: string
{
    use HasMeta;

    case Mock = 'mock';
    case Marqeta = 'marqeta';
    // Future adapters: case Stripe = 'stripe'; case Lithic = 'lithic'; …

    public function isSimulated(): bool
    {
        return $this === self::Mock;
    }

    /** Drivers that actually have an adapter configured in config/card.php. @return list<self> */
    public static function configured(): array
    {
        $keys = array_keys((array) config('card.providers', []));

        return array_values(array_filter(self::cases(), fn (self $d) => in_array($d->value, $keys, true)));
    }
}
