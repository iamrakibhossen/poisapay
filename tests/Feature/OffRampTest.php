<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Domain\Ramp\RequestOffRampAction;
use App\Domain\Ramp\SettleOffRampAction;
use App\Enums\KycTier;
use App\Enums\LedgerAccountType;
use App\Enums\RampRail;
use App\Enums\RampStatus;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->bdt = Asset::firstOrCreate(
        ['symbol' => 'BDT', 'chain_id' => null, 'contract_address' => null],
        ['name' => 'Bangladeshi Taka', 'kind' => 'fiat', 'currency_code' => 'BDT', 'decimals' => 2,
            'is_active' => true, 'withdrawal_min' => '0', 'withdrawal_fee' => '0'],
    );
    app(AccountResolver::class)->ensureSystemAccounts($this->bdt->id);
    $this->ledger = app(LedgerService::class);
    $this->resolver = app(AccountResolver::class);

    $this->user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $this->user->forceFill(['created_at' => now()->subMonth()])->save();
    creditUser($this->user, $this->bdt, '500000'); // 5,000.00 BDT
});

function clearingBalance(AccountResolver $resolver, int $assetId): string
{
    return $resolver->system(LedgerAccountType::RampClearing, $assetId)->fresh('balance')->money()->baseString();
}

it('reserves funds and submits the payout when an off-ramp is requested', function () {
    $order = app(RequestOffRampAction::class)->execute(
        $this->user, $this->bdt, $this->bdt->money('100000'), RampRail::MobileWallet,
        'offramp-1', 'bKash •••7806', ['number' => '017...'],
    );

    expect($order->status)->toBe(RampStatus::Confirmed)
        ->and($order->provider_ref)->toStartWith('stub_')
        ->and($this->ledger->availableBalance($this->user, $this->bdt->id)->baseString())->toBe('400000')
        ->and($this->ledger->lockedBalance($this->user, $this->bdt->id)->baseString())->toBe('100000');
});

it('is idempotent on the client key', function () {
    $action = app(RequestOffRampAction::class);
    $a = $action->execute($this->user, $this->bdt, $this->bdt->money('100000'), RampRail::MobileWallet, 'dupe-key');
    $b = $action->execute($this->user, $this->bdt, $this->bdt->money('100000'), RampRail::MobileWallet, 'dupe-key');

    expect($b->id)->toBe($a->id)
        ->and($this->ledger->lockedBalance($this->user, $this->bdt->id)->baseString())->toBe('100000');
});

it('settles the payout on a success webhook (locked -> clearing, money leaves)', function () {
    $order = app(RequestOffRampAction::class)->execute(
        $this->user, $this->bdt, $this->bdt->money('100000'), RampRail::MobileWallet, 'offramp-ok',
    );
    $clearingBefore = clearingBalance($this->resolver, $this->bdt->id);

    $settled = app(SettleOffRampAction::class)->settle($order->fresh());

    expect($settled->status)->toBe(RampStatus::Credited)
        ->and($this->ledger->lockedBalance($this->user, $this->bdt->id)->baseString())->toBe('0')
        ->and($this->ledger->availableBalance($this->user, $this->bdt->id)->baseString())->toBe('400000')
        ->and(ltrim(bcsub(clearingBalance($this->resolver, $this->bdt->id), $clearingBefore), '-'))->toBe('100000');
});

it('releases the reservation on a failure webhook (locked -> available)', function () {
    $order = app(RequestOffRampAction::class)->execute(
        $this->user, $this->bdt, $this->bdt->money('100000'), RampRail::MobileWallet, 'offramp-fail',
    );

    app(SettleOffRampAction::class)->fail($order->fresh());

    expect($order->fresh()->status)->toBe(RampStatus::Failed)
        ->and($this->ledger->availableBalance($this->user, $this->bdt->id)->baseString())->toBe('500000')
        ->and($this->ledger->lockedBalance($this->user, $this->bdt->id)->baseString())->toBe('0');
});

it('drives settlement through the provider-agnostic webhook endpoint', function () {
    $order = app(RequestOffRampAction::class)->execute(
        $this->user, $this->bdt, $this->bdt->money('100000'), RampRail::MobileWallet, 'offramp-http',
    );

    $this->postJson('/api/ramp/payout/webhook/stub', [
        'provider_ref' => $order->provider_ref,
        'outcome' => 'succeeded',
    ])->assertOk()->assertJson(['ok' => true]);

    expect($order->fresh()->status)->toBe(RampStatus::Credited)
        ->and($this->ledger->lockedBalance($this->user, $this->bdt->id)->baseString())->toBe('0');
});

it('rejects a cash-out that exceeds the available balance', function () {
    app(RequestOffRampAction::class)->execute(
        $this->user, $this->bdt, $this->bdt->money('900000'), RampRail::BankTransfer, 'too-big',
    );
})->throws(ValidationException::class);
