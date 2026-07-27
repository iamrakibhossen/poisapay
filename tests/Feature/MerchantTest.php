<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Domain\Merchant\CreateInvoiceAction;
use App\Domain\Merchant\PayInvoiceAction;
use App\Models\MerchantInvoice;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->merchant = User::factory()->create();
    $this->payer = User::factory()->create();
});

it('creates an invoice idempotently by reference', function () {
    $a = app(CreateInvoiceAction::class)->execute($this->merchant, $this->asset, Money::ofBase('5000000', 6, 'USDT'), 'ORDER-1');
    $b = app(CreateInvoiceAction::class)->execute($this->merchant, $this->asset, Money::ofBase('5000000', 6, 'USDT'), 'ORDER-1');

    expect($b->id)->toBe($a->id)
        ->and(MerchantInvoice::count())->toBe(1);
});

it('pays an invoice: payer -> merchant net of the 1% platform fee', function () {
    creditUser($this->payer, $this->asset, '10000000');
    $invoice = app(CreateInvoiceAction::class)->execute($this->merchant, $this->asset, Money::ofBase('5000000', 6, 'USDT'), 'ORDER-2');

    app(PayInvoiceAction::class)->execute($this->payer, $invoice);

    // Payer pays gross 5.0; merchant nets 4.95; platform keeps 0.05 fee.
    expect($this->ledger->availableBalance($this->payer, $this->asset->id)->baseString())->toBe('5000000')
        ->and($this->ledger->availableBalance($this->merchant, $this->asset->id)->baseString())->toBe('4950000')
        ->and($invoice->fresh()->fee_amount)->toBe('50000')
        ->and($invoice->fresh()->status)->toBe('paid');
});

it('is idempotent — paying twice does not double-charge', function () {
    creditUser($this->payer, $this->asset, '10000000');
    $invoice = app(CreateInvoiceAction::class)->execute($this->merchant, $this->asset, Money::ofBase('5000000', 6, 'USDT'), 'ORDER-3');
    $action = app(PayInvoiceAction::class);

    $action->execute($this->payer, $invoice);
    $action->execute($this->payer, $invoice->fresh());

    expect($this->ledger->availableBalance($this->payer, $this->asset->id)->baseString())->toBe('5000000');
});

it('rejects payment with insufficient balance', function () {
    creditUser($this->payer, $this->asset, '1000000');
    $invoice = app(CreateInvoiceAction::class)->execute($this->merchant, $this->asset, Money::ofBase('5000000', 6, 'USDT'), 'ORDER-4');

    app(PayInvoiceAction::class)->execute($this->payer, $invoice);
})->throws(RuntimeException::class);

it('pays a USDT invoice from an ETH balance when the Spending Engine is on', function () {
    updateSetting('spending_engine_enabled', true);
    $eth = testAsset('ETH', 18, 'ethereum');
    seedInventory($this->asset, '100000000000'); // house USDT liquidity
    creditUser($this->payer, $eth, '1000000000000000000'); // 1 ETH, no USDT

    $invoice = app(CreateInvoiceAction::class)->execute($this->merchant, $this->asset, Money::ofBase('5000000', 6, 'USDT'), 'ORDER-ENG');
    app(PayInvoiceAction::class)->execute($this->payer, $invoice);

    // Merchant nets 4.95 USDT and the ETH was auto-converted to fund it.
    expect($this->ledger->availableBalance($this->merchant, $this->asset->id)->baseString())->toBe('4950000')
        ->and($invoice->fresh()->status)->toBe('paid')
        ->and($this->ledger->availableBalance($this->payer, $eth->id)->isLessThan($eth->money('1000000000000000000')))->toBeTrue();
});
