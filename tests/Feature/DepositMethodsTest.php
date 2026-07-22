<?php

declare(strict_types=1);

use App\Domain\Deposit\CreditManualDepositAction;
use App\Domain\Deposit\SubmitManualDepositAction;
use App\Domain\Ledger\LedgerService;
use App\Enums\DepositStatus;
use App\Models\Asset;
use App\Models\Deposit;
use App\Models\DepositMethod;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->user = User::factory()->create();
    $this->method = DepositMethod::create([
        'asset_id' => $this->asset->id, 'name' => 'Test Bank', 'type' => 'bank',
        'details' => ['bank_name' => 'City Bank'], 'min_amount' => '1000000', 'is_active' => true, 'sort' => 0,
    ]);
});

function submitDeposit($user, $method, string $base, string $ref = 'TXN-1'): Deposit
{
    return app(SubmitManualDepositAction::class)->execute($user, $method, Money::ofBase($base, 6, 'USDT'), $ref);
}

it('records a manual deposit as pending with no ledger movement', function () {
    $deposit = submitDeposit($this->user, $this->method, '5000000');

    expect($deposit->status)->toBe(DepositStatus::Detected)
        ->and($deposit->source)->toBe('manual')
        ->and($deposit->deposit_method_id)->toBe($this->method->id)
        ->and($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('0');
});

it('credits a manual deposit on operator approval', function () {
    $deposit = submitDeposit($this->user, $this->method, '5000000');

    app(CreditManualDepositAction::class)->execute($deposit);

    expect($deposit->fresh()->status)->toBe(DepositStatus::Credited)
        ->and($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('5000000');
});

it('lands a manual deposit in treasury:hot (settled, already in the company account)', function () {
    $deposit = submitDeposit($this->user, $this->method, '5000000');
    app(CreditManualDepositAction::class)->execute($deposit);

    $resolver = $this->ledger->resolver();
    $balance = fn ($type) => (string) (\Illuminate\Support\Facades\DB::table('account_balances')
        ->where('account_id', $resolver->system($type, $this->asset->id)->id)->value('balance') ?? '0');

    // The cash is booked to hot (settled), not pending (which is for crypto confirmations).
    expect($balance(\App\Enums\LedgerAccountType::TreasuryHot))->toBe('5000000')
        ->and($balance(\App\Enums\LedgerAccountType::TreasuryPending))->toBe('0');
});

it('is idempotent — approving twice credits once', function () {
    $deposit = submitDeposit($this->user, $this->method, '5000000');
    $action = app(CreditManualDepositAction::class);

    $action->execute($deposit);
    $action->execute($deposit->fresh());

    expect($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('5000000');
});

it('rejects a manual deposit without crediting', function () {
    $deposit = submitDeposit($this->user, $this->method, '5000000');

    app(CreditManualDepositAction::class)->reject($deposit, null, 'no proof');

    expect($deposit->fresh()->status)->toBe(DepositStatus::Orphaned)
        ->and($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('0');
});

it('enforces the method minimum', function () {
    submitDeposit($this->user, $this->method, '500000'); // below 1,000,000 min
})->throws(RuntimeException::class);

it('blocks a deposit when the asset is not deposit-enabled', function () {
    $this->asset->update(['deposit_enabled' => false]);

    submitDeposit($this->user, $this->method, '5000000');
})->throws(RuntimeException::class);

it('excludes non-depositable assets from the depositable scope', function () {
    $blocked = Asset::firstOrCreate(
        ['symbol' => 'BDT', 'chain_id' => null, 'contract_address' => null],
        ['name' => 'Taka', 'kind' => 'fiat', 'currency_code' => 'BDT', 'decimals' => 2, 'is_active' => true],
    );
    $blocked->update(['deposit_enabled' => false]);

    expect(Asset::depositable()->pluck('id'))->not->toContain($blocked->id)
        ->and(Asset::depositable()->pluck('id'))->toContain($this->asset->id);
});
