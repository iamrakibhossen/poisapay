<?php

declare(strict_types=1);

namespace App\Domain\Spending\Enums;

use App\Enums\Concerns\HasMeta;

/**
 * Why a spend is happening. Every balance-spending feature declares its purpose
 * so the resulting ledger entry, audit trail and history are self-describing —
 * and so new spend surfaces route through the one engine instead of hand-rolling
 * their own selection logic.
 */
enum SpendPurpose: string
{
    use HasMeta;

    case CardPurchase = 'card_purchase';
    case CardSettlement = 'card_settlement';
    case MerchantPayment = 'merchant_payment';
    case ShopCheckout = 'shop_checkout';
    case PaymentLink = 'payment_link';
    case QrPayment = 'qr_payment';
    case Subscription = 'subscription';
    case BillPayment = 'bill_payment';
    case Invoice = 'invoice';
    case InternalCheckout = 'internal_checkout';

    /** The ledger entry `type` this purpose posts under. */
    public function ledgerType(): string
    {
        return match ($this) {
            self::CardPurchase => 'card.issue.fee',
            self::CardSettlement => 'card.settle',
            self::MerchantPayment => 'merchant.pay',
            self::ShopCheckout => 'shop.checkout',
            self::PaymentLink => 'payment_link.pay',
            self::QrPayment => 'qr.pay',
            self::Subscription => 'subscription.charge',
            self::BillPayment => 'bill.pay',
            self::Invoice => 'merchant.invoice.pay',
            self::InternalCheckout => 'internal.checkout',
        };
    }
}
