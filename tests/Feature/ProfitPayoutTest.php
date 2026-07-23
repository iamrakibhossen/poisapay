<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Ledger\WithdrawProfitAction;
use App\Domain\Reconciliation\ReconciliationService;
use App\Enums\LedgerAccountType;
use App\Models\Admin;
use App\Models\ProfitPayout;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->resolver = app(AccountResolver::class);
    $this->action = app(WithdrawProfitAction::class);
    $this->admin = Admin::create(['name' => 'Op', 'email' => 'rev@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
});

function seedFeeIncome($ledger, $resolver, $asset, string $base): void
{
    $pending = $resolver->system(LedgerAccountType::TreasuryPending, $asset->id);
    $fee = $resolver->system(LedgerAccountType::FeeIncome, $asset->id);
    $ledger->post(new EntryData(
        type: 'test.fee',
        idempotencyKey: 'test:fee:'.uniqid('', true),
        lines: [
            PostingLine::debit($pending->id, $asset->id, $base),
            PostingLine::credit($fee->id, $asset->id, $base),
        ],
    ));
}

function ownerPayoutBalance($resolver, $assetId): string
{
    $acct = $resolver->system(LedgerAccountType::OwnerPayout, $assetId);

    return (string) (DB::table('account_balances')->where('account_id', $acct->id)->value('balance') ?? '0');
}

it('reports available profit from fee income', function () {
    seedFeeIncome($this->ledger, $this->resolver, $this->usdt, '1000000');

    expect($this->action->availableProfit($this->usdt)->baseString())->toBe('1000000');
});

it('withdraws profit into owner:payout and records a payout', function () {
    seedFeeIncome($this->ledger, $this->resolver, $this->usdt, '1000000');

    $payout = $this->action->execute($this->admin, $this->usdt, Money::ofBase('400000', 6, 'USDT'), 'TdestExchange1', 'Q1 draw');

    expect($this->action->availableProfit($this->usdt)->baseString())->toBe('600000')
        ->and(ownerPayoutBalance($this->resolver, $this->usdt->id))->toBe('400000')
        ->and($payout->amount)->toBe('400000')
        ->and($payout->destination)->toBe('TdestExchange1')
        ->and(ProfitPayout::count())->toBe(1);
});

it('broadcasts a crypto payout with a tx hash + completed status', function () {
    seedFeeIncome($this->ledger, $this->resolver, $this->usdt, '1000000');

    $payout = $this->action->execute($this->admin, $this->usdt, Money::ofBase('400000', 6, 'USDT'), 'TdestExchange1');

    expect($payout->status)->toBe('completed')
        ->and($payout->tx_hash)->toStartWith('0x')
        ->and($payout->network)->toBe($this->usdt->chain->name)
        ->and($payout->destination_address)->toBe('TdestExchange1')
        ->and($payout->completed_at)->not->toBeNull();
});

it('actually moves the backing crypto out of the treasury', function () {
    seedFeeIncome($this->ledger, $this->resolver, $this->usdt, '1000000');

    $treasuryBalance = function (LedgerAccountType $type) {
        $acct = $this->resolver->system($type, $this->usdt->id);

        return (string) (DB::table('account_balances')->where('account_id', $acct->id)->value('balance') ?? '0');
    };

    // Seed put 1,000,000 into treasury:pending; nothing sent out yet.
    expect($treasuryBalance(LedgerAccountType::TreasuryPending))->toBe('1000000')
        ->and($treasuryBalance(LedgerAccountType::TreasuryOut))->toBe('0');

    $this->action->execute($this->admin, $this->usdt, Money::ofBase('400000', 6, 'USDT'), 'Binance');

    // Reserves dropped by the amount taken; treasury:out records the outflow.
    expect($treasuryBalance(LedgerAccountType::TreasuryPending))->toBe('600000')
        ->and($treasuryBalance(LedgerAccountType::TreasuryOut))->toBe('400000');

    // Solvency still holds: treasury ≥ user liabilities.
    $run = app(ReconciliationService::class)->runForAsset($this->usdt);
    expect($run->is_solvent)->toBeTrue();
});

it('rejects withdrawing more than the available profit', function () {
    seedFeeIncome($this->ledger, $this->resolver, $this->usdt, '1000000');

    $this->action->execute($this->admin, $this->usdt, Money::ofBase('2000000', 6, 'USDT'));
})->throws(RuntimeException::class);

it('does not touch user funds', function () {
    $user = User::factory()->create();
    creditUser($user, $this->usdt, '5000000');
    seedFeeIncome($this->ledger, $this->resolver, $this->usdt, '1000000');

    $this->action->execute($this->admin, $this->usdt, Money::ofBase('1000000', 6, 'USDT'));

    // The user's balance is untouched by a profit withdrawal.
    expect($this->ledger->availableBalance($user, $this->usdt->id)->baseString())->toBe('5000000');
});
