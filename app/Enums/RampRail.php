<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\HasMeta;

/** Local BDT payment rails (TDD §F1.2). */
enum RampRail: string
{
    use HasMeta;

    case BankTransfer = 'bank_transfer';
    case MobileWallet = 'mobile_wallet';
    case CardTopup = 'card_topup';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank Transfer',
            self::MobileWallet => 'Mobile Wallet (bKash / Nagad)',
            self::CardTopup => 'Card Top-up',
        };
    }
}
