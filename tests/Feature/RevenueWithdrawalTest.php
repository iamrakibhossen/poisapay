<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Revenue\ProcessRevenueWithdrawalAction;
use App\Domain\Revenue\RequestRevenueWithdrawalAction;
use App\Domain\Revenue\RevenueService;
use App\Enums\LedgerAccountType;
use App\Enums\RevenueWithdrawalStatus;
use App\Jobs\BroadcastRevenueWithdrawalJob;
use App\Models\Admin;
use App\Support\Money;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->resolver = app(AccountResolver::class);
    $this->revenue = app(RevenueService::class);
    $this->process = app(ProcessRevenueWithdrawalAction::class);
    $this->admin = Admin::create(['name' => 'Op', 'email' => 'fin@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $this->approver = Admin::create(['name' => 'Boss', 'email' => 'boss@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
});

function seedRevenue($ledger, $resolver, $asset, string $base, LedgerAccountType $type = LedgerAccountType::FeeIncome): void
{
    $pending = $resolver->system(LedgerAccountType::TreasuryPending, $asset->id);
    $fee = $resolver->system($type, $asset->id);
    $ledger->post(new EntryData(
        type: 'test.fee',
        idempotencyKey: 'test:rev:'.uniqid('', true),
        lines: [PostingLine::debit($pending->id, $asset->id, $base), PostingLine::credit($fee->id, $asset->id, $base)],
    ));
}

it('reflects collected fees as the revenue balance', function () {
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000', LedgerAccountType::FeeCard);
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '500000', LedgerAccountType::FxSpreadIncome);

    expect($this->revenue->balance($this->usdt)->baseString())->toBe('1500000')
        ->and($this->revenue->stats($this->usdt)['lifetime']->baseString())->toBe('1500000');
});

it('records a pending withdrawal without moving money', function () {
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000');

    $w = app(RequestRevenueWithdrawalAction::class)->execute($this->admin, $this->usdt, Money::ofBase('400000', 6, 'USDT'), 'ethereum', '0xabc');

    expect($w->status)->toBe(RevenueWithdrawalStatus::Pending)
        ->and($this->revenue->balance($this->usdt)->baseString())->toBe('1000000');
});

it('approves: moves revenue out of the wallet and queues the broadcast', function () {
    Queue::fake();
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000');
    $w = app(RequestRevenueWithdrawalAction::class)->execute($this->admin, $this->usdt, Money::ofBase('400000', 6, 'USDT'), 'ethereum', '0xabc');

    $this->process->approve($w, $this->approver);

    expect($w->fresh()->status)->toBe(RevenueWithdrawalStatus::Approved)
        ->and($w->fresh()->entry_id)->not->toBeNull()
        ->and($this->revenue->balance($this->usdt)->baseString())->toBe('600000');
    Queue::assertPushed(BroadcastRevenueWithdrawalJob::class);
});

it('completes via the broadcast job with a tx hash', function () {
    Queue::fake();
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000');
    $w = app(RequestRevenueWithdrawalAction::class)->execute($this->admin, $this->usdt, Money::ofBase('400000', 6, 'USDT'), 'ethereum', '0xabc');
    $this->process->approve($w, $this->approver);

    (new BroadcastRevenueWithdrawalJob($w->id))->handle($this->process);

    expect($w->fresh()->status)->toBe(RevenueWithdrawalStatus::Completed)
        ->and($w->fresh()->tx_hash)->toStartWith('0x');
});

it('reverses the ledger entry when a withdrawal fails', function () {
    Queue::fake();
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000');
    $w = app(RequestRevenueWithdrawalAction::class)->execute($this->admin, $this->usdt, Money::ofBase('400000', 6, 'USDT'), 'ethereum', '0xabc');
    $this->process->approve($w, $this->approver);

    $this->process->markFailed($w, 'network error');

    expect($w->fresh()->status)->toBe(RevenueWithdrawalStatus::Failed)
        ->and($this->revenue->balance($this->usdt)->baseString())->toBe('1000000'); // returned
});

it('rejects a withdrawal above the revenue balance', function () {
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000');

    app(RequestRevenueWithdrawalAction::class)->execute($this->admin, $this->usdt, Money::ofBase('2000000', 6, 'USDT'), 'ethereum', '0xabc');
})->throws(RuntimeException::class);
