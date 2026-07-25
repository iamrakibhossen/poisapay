<?php

declare(strict_types=1);

namespace App\Sell\Enums;

/**
 * Product delivery type. Extensible: add a case + its behaviour flags — no schema
 * change (type-specific data lives in sell_products.attributes jsonb).
 */
enum ProductType: string
{
    case Digital = 'digital';
    case Physical = 'physical';
    case License = 'license';
    case Membership = 'membership';
    case Subscription = 'subscription';
    case Service = 'service';
    case Bundle = 'bundle';

    public function requiresShipping(): bool
    {
        return $this === self::Physical;
    }

    public function isRecurring(): bool
    {
        return in_array($this, [self::Membership, self::Subscription], true);
    }

    /** Delivered by a downloadable file / license grant. */
    public function isDigitalDelivery(): bool
    {
        return in_array($this, [self::Digital, self::License], true);
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
