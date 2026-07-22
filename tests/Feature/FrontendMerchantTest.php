<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Domain\Merchant\CreateInvoiceAction;
use App\Domain\Merchant\RegisterMerchantAction;
use App\Enums\KycTier;
use App\Models\Merchant;
use App\Models\MerchantInvoice;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->merchant = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $this->payer = User::factory()->create(['kyc_tier' => KycTier::Full]);
});

/** Register $this->merchant as an active merchant (auto-approve default is on). */
function makeMerchant(User $user): Merchant
{
    return app(RegisterMerchantAction::class)->execute($user, [
        'business_name' => 'Acme Co.',
    ]);
}

it('renders the merchant console page', function () {
    actingAs($this->merchant)->get(route('merchant'))->assertOk();
});

it('renders the merchant console with the merchant profile once registered', function () {
    makeMerchant($this->merchant);

    actingAs($this->merchant)->get(route('merchant'))
        ->assertOk()
        ->assertSee('Acme Co.');
});

it('renders the pay invoice page and 404s for an unknown invoice', function () {
    $invoice = app(CreateInvoiceAction::class)->execute(
        $this->merchant, $this->asset, Money::ofBase('1000000', 6, 'USDT'), 'PAGE-1'
    );

    actingAs($this->payer)->get(route('pay.invoice', $invoice->id))->assertOk()->assertSee('PAGE-1');
    actingAs($this->payer)->get(route('pay.invoice', Str::uuid()->toString()))->assertNotFound();
});

it('register creates a merchant for the authenticated user and redirects', function () {
    actingAs($this->merchant)->post(route('merchant.register'), [
        'businessName' => 'My Shop',
    ])->assertRedirect(route('merchant'))->assertSessionHas('success');

    expect(Merchant::where('user_id', $this->merchant->id)->where('business_name', 'My Shop')->exists())->toBeTrue();
});

it('register is blocked for a non-full-kyc user', function () {
    $basic = User::factory()->create(['kyc_tier' => KycTier::Basic]);

    actingAs($basic)->post(route('merchant.register'), [
        'businessName' => 'Nope Inc.',
    ])->assertSessionHasErrors('businessName');

    expect(Merchant::where('user_id', $basic->id)->exists())->toBeFalse();
});

it('createInvoice creates an invoice for the merchant and redirects', function () {
    makeMerchant($this->merchant);

    actingAs($this->merchant)->post(route('merchant.invoice.create'), [
        'assetId' => $this->asset->id,
        'amount' => '5',
        'reference' => 'API-ORDER-1',
    ])->assertRedirect(route('merchant'))->assertSessionHas('success');

    $invoice = MerchantInvoice::where('merchant_id', $this->merchant->id)->where('reference', 'API-ORDER-1')->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->amount)->toBe('5000000');
});

it('pay moves ledger funds from payer to merchant net of fee', function () {
    creditUser($this->payer, $this->asset, '10000000');
    $invoice = app(CreateInvoiceAction::class)->execute(
        $this->merchant, $this->asset, Money::ofBase('5000000', 6, 'USDT'), 'PAY-API-1'
    );

    actingAs($this->payer)->post(route('pay.execute', $invoice->id))
        ->assertRedirect(route('pay.invoice', $invoice->id))->assertSessionHas('success');

    // Payer 10.0 - 5.0 = 5.0; merchant nets 4.95 (1% fee); invoice paid.
    expect($this->ledger->availableBalance($this->payer, $this->asset->id)->baseString())->toBe('5000000')
        ->and($this->ledger->availableBalance($this->merchant, $this->asset->id)->baseString())->toBe('4950000')
        ->and($invoice->fresh()->status)->toBe('paid');
});

it('pay rejects insufficient balance with a validation error', function () {
    creditUser($this->payer, $this->asset, '1000000');
    $invoice = app(CreateInvoiceAction::class)->execute(
        $this->merchant, $this->asset, Money::ofBase('5000000', 6, 'USDT'), 'PAY-API-2'
    );

    actingAs($this->payer)->post(route('pay.execute', $invoice->id))->assertSessionHasErrors('invoice');

    expect($invoice->fresh()->status)->toBe('pending');
});

it('a non-owner cannot cancel or refund an invoice', function () {
    $invoice = app(CreateInvoiceAction::class)->execute(
        $this->merchant, $this->asset, Money::ofBase('5000000', 6, 'USDT'), 'SCOPE-1'
    );

    $other = User::factory()->create(['kyc_tier' => KycTier::Full]);

    actingAs($other)->post(route('merchant.invoice.cancel', $invoice->id))->assertNotFound();
    actingAs($other)->post(route('merchant.invoice.refund', $invoice->id))->assertNotFound();

    // Untouched.
    expect($invoice->fresh()->status)->toBe('pending');
});

it('the owner can cancel their own pending invoice', function () {
    makeMerchant($this->merchant);
    $invoice = app(CreateInvoiceAction::class)->execute(
        $this->merchant, $this->asset, Money::ofBase('5000000', 6, 'USDT'), 'CANCEL-1'
    );

    actingAs($this->merchant)->post(route('merchant.invoice.cancel', $invoice->id))
        ->assertRedirect(route('merchant'))->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe('cancelled');
});
