<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Domain\Merchant\CancelInvoiceAction;
use App\Domain\Merchant\CreateInvoiceAction;
use App\Domain\Merchant\PayInvoiceAction;
use App\Domain\Merchant\RefundInvoiceAction;
use App\Domain\Merchant\RegisterMerchantAction;
use App\Domain\Merchant\SetMerchantStatusAction;
use App\Enums\KycTier;
use App\Enums\LedgerAccountType;
use App\Enums\MerchantStatus;
use App\Models\Merchant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->merchantUser = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $this->payer = User::factory()->create();
});

function systemBalanceM(LedgerAccountType $type, int $assetId): string
{
    $account = app(AccountResolver::class)->system($type, $assetId);

    return (string) (DB::table('account_balances')->where('account_id', $account->id)->value('balance') ?? '0');
}

it('registers a merchant and auto-approves when configured', function () {
    $merchant = app(RegisterMerchantAction::class)->execute($this->merchantUser, [
        'business_name' => 'Acme Coffee',
        'category' => 'food',
    ]);

    expect($merchant->status)->toBe(MerchantStatus::Active)
        ->and($merchant->slug)->toBe('acme-coffee')
        ->and($merchant->approved_at)->not->toBeNull()
        ->and($this->merchantUser->fresh()->isMerchant())->toBeTrue();
});

it('requires full KYC to register as a merchant', function () {
    $basic = User::factory()->create(['kyc_tier' => KycTier::Unverified]);

    app(RegisterMerchantAction::class)->execute($basic, ['business_name' => 'No KYC Co']);
})->throws(RuntimeException::class);

it('lands in the pending queue when auto-approve is off', function () {
    updateSetting('merchant_auto_approve', false, 'merchant');

    $merchant = app(RegisterMerchantAction::class)->execute($this->merchantUser, ['business_name' => 'Pending Co']);

    expect($merchant->status)->toBe(MerchantStatus::Pending)
        ->and($merchant->approved_at)->toBeNull();
});

it('applies a per-merchant fee override on payment', function () {
    $merchant = app(RegisterMerchantAction::class)->execute($this->merchantUser, ['business_name' => 'HiFee Co']);
    $merchant->update(['fee_bps' => 250]); // 2.5%

    creditUser($this->payer, $this->asset, '10000000');
    $invoice = app(CreateInvoiceAction::class)->execute($this->merchantUser, $this->asset, Money::ofBase('4000000', 6, 'USDT'), 'OV-1');

    app(PayInvoiceAction::class)->execute($this->payer, $invoice);

    // 2.5% of 4.0 = 0.1 fee; merchant nets 3.9.
    expect($this->ledger->availableBalance($this->merchantUser, $this->asset->id)->baseString())->toBe('3900000')
        ->and($invoice->fresh()->fee_amount)->toBe('100000')
        ->and(systemBalanceM(LedgerAccountType::FeeIncome, $this->asset->id))->toBe('100000');
});

it('refunds a paid invoice, making the payer whole for the gross', function () {
    app(RegisterMerchantAction::class)->execute($this->merchantUser, ['business_name' => 'Refund Co']);
    creditUser($this->payer, $this->asset, '5000000');
    $invoice = app(CreateInvoiceAction::class)->execute($this->merchantUser, $this->asset, Money::ofBase('5000000', 6, 'USDT'), 'RF-1');
    app(PayInvoiceAction::class)->execute($this->payer, $invoice);

    app(RefundInvoiceAction::class)->execute($invoice->fresh());

    // Payer back to full 5.0; merchant back to 0; platform fee returned.
    expect($this->ledger->availableBalance($this->payer, $this->asset->id)->baseString())->toBe('5000000')
        ->and($this->ledger->availableBalance($this->merchantUser, $this->asset->id)->baseString())->toBe('0')
        ->and(systemBalanceM(LedgerAccountType::FeeIncome, $this->asset->id))->toBe('0')
        ->and($invoice->fresh()->status)->toBe('refunded');
});

it('refund is idempotent', function () {
    app(RegisterMerchantAction::class)->execute($this->merchantUser, ['business_name' => 'Idem Co']);
    creditUser($this->payer, $this->asset, '5000000');
    $invoice = app(CreateInvoiceAction::class)->execute($this->merchantUser, $this->asset, Money::ofBase('5000000', 6, 'USDT'), 'RF-2');
    app(PayInvoiceAction::class)->execute($this->payer, $invoice);
    $refund = app(RefundInvoiceAction::class);

    $refund->execute($invoice->fresh());
    $refund->execute($invoice->fresh());

    expect($this->ledger->availableBalance($this->payer, $this->asset->id)->baseString())->toBe('5000000');
});

it('blocks payment to a suspended merchant', function () {
    $merchant = app(RegisterMerchantAction::class)->execute($this->merchantUser, ['business_name' => 'Bad Co']);
    app(SetMerchantStatusAction::class)->execute($merchant, MerchantStatus::Suspended, 'fraud review');
    creditUser($this->payer, $this->asset, '5000000');
    $invoice = app(CreateInvoiceAction::class)->execute($this->merchantUser, $this->asset, Money::ofBase('1000000', 6, 'USDT'), 'SUS-1');

    app(PayInvoiceAction::class)->execute($this->payer, $invoice);
})->throws(RuntimeException::class);

it('cancels a pending invoice but not a paid one', function () {
    creditUser($this->payer, $this->asset, '5000000');
    $invoice = app(CreateInvoiceAction::class)->execute($this->merchantUser, $this->asset, Money::ofBase('1000000', 6, 'USDT'), 'CAN-1');

    app(CancelInvoiceAction::class)->execute($invoice);
    expect($invoice->fresh()->status)->toBe('cancelled');

    $paid = app(CreateInvoiceAction::class)->execute($this->merchantUser, $this->asset, Money::ofBase('1000000', 6, 'USDT'), 'CAN-2');
    app(PayInvoiceAction::class)->execute($this->payer, $paid);

    expect(fn () => app(CancelInvoiceAction::class)->execute($paid->fresh()))->toThrow(RuntimeException::class);
});

it('cannot register the same merchant twice', function () {
    app(RegisterMerchantAction::class)->execute($this->merchantUser, ['business_name' => 'Once Co']);

    app(RegisterMerchantAction::class)->execute($this->merchantUser, ['business_name' => 'Twice Co']);
})->throws(RuntimeException::class);
