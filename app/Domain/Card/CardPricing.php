<?php

declare(strict_types=1);

namespace App\Domain\Card;

use App\Enums\CardType;

/**
 * One-time card issuance pricing. Virtual and physical are priced independently
 * and admin-configurable via the settings engine (`card_price_virtual` /
 * `card_price_physical`), falling back to config/card.php → `pricing`. A price of
 * 0 means the card is free. Prices are quoted and displayed in USD.
 */
final class CardPricing
{
    /** Price for a card type, in major units of the display currency. */
    public static function price(CardType $type): float
    {
        return (float) getSetting('card_price_'.$type->value, config('card.pricing.'.$type->value, 0));
    }

    /** Price in minor units (cents) — the canonical integer used for charging. */
    public static function priceMinor(CardType $type): int
    {
        return (int) round(self::price($type) * 100);
    }

    public static function isFree(CardType $type): bool
    {
        return self::priceMinor($type) <= 0;
    }

    public static function currency(): string
    {
        return (string) config('card.pricing.currency', 'USD');
    }

    /** Stablecoin symbol the fee is drawn from (value-of-USD proxy). */
    public static function fundingAsset(): string
    {
        return (string) config('card.pricing.funding_asset', 'USDT');
    }

    /** Human label in USD, e.g. "$9.99" or "Free". */
    public static function label(CardType $type): string
    {
        return self::isFree($type)
            ? __('Free')
            : '$'.number_format(self::price($type), 2);
    }
}
