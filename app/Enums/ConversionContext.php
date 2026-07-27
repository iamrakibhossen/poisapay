<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

enum ConversionContext: string
{
    use HasMeta;

    case Swap = 'swap';
    case Ramp = 'ramp';
    case CardSettle = 'card_settle';
    // Global Spending Engine auto-conversion. Like CardSettle/Ramp it may cross
    // crypto↔fiat (the crypto-only guard is scoped to Swap), but a user can never
    // trigger it manually — it fires only inside SpendingEngine to fund a spend.
    case Spend = 'spend';
}
