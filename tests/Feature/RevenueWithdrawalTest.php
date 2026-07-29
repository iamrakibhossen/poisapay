<?php

declare(strict_types=1);

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\Evm;
use App\Domain\Chain\Evm\FakeBlockchainProvider;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Revenue\ProcessRevenueWithdrawalAction;
use App\Domain\Revenue\RequestRevenueWithdrawalAction;
use App\Domain\Revenue\RevenueService;
use App\Domain\Withdrawal\Exceptions\InvalidWithdrawalAddressException;
use App\Enums\ChainType;
use App\Enums\LedgerAccountType;
use App\Enums\RevenueWithdrawalStatus;
use App\Jobs\BroadcastRevenueWithdrawalJob;
use App\Models\Admin;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\Currency;
use App\Models\GasWallet;
use App\Support\Money;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // Live custody with a deterministic seed + the in-memory chain, so revenue
    // payouts run through the REAL signer/broadcaster (never a fabricated hash).
    config([
        'poisapay.custody_simulated' => false,
        'poisapay.custody.seed' => str_repeat('a1', 32),
        'providers.blockchain.driver' => 'fake',
    ]);
    app()->forgetInstance(BlockchainProvider::class);
    $this->fake = app(BlockchainProvider::class);
    expect($this->fake)->toBeInstanceOf(FakeBlockchainProvider::class);

    $this->chain = Chain::create([
        'key' => 'ethereum', 'name' => 'Ethereum', 'native_symbol' => 'ETH',
        'min_confirmations' => 12, 'is_evm' => true, 'is_active' => true,
    ]);
    $currency = Currency::firstOrCreate(['symbol' => 'USDT'], ['name' => 'Tether', 'kind' => 'crypto', 'is_stablecoin' => true, 'is_active' => true]);
    $this->contract = strtolower((string) config('poisapay.custody.ethereum.usdt_contract'));
    $this->usdt = Asset::create([
        'currency_id' => $currency->id, 'symbol' => 'USDT', 'name' => 'USDT', 'kind' => 'crypto',
        'chain_id' => $this->chain->id, 'contract_address' => $this->contract, 'decimals' => 6,
        'is_stablecoin' => true, 'is_active' => true, 'withdrawal_min' => '0', 'withdrawal_fee' => '0',
    ]);
    app(AccountResolver::class)->ensureSystemAccounts($this->usdt->id);

    // A funded, healthy gas wallet so custody readiness passes.
    $hot = app(SignerKeyProvider::class)->hotWalletAddress(ChainType::Ethereum);
    GasWallet::create([
        'chain_id' => $this->chain->id, 'address' => $hot, 'balance' => '1000000000000000000',
        'min_threshold' => '0', 'critical_threshold' => '0', 'healthy_threshold' => '0', 'is_active' => true,
    ]);

    $this->ledger = app(LedgerService::class);
    $this->resolver = app(AccountResolver::class);
    $this->revenue = app(RevenueService::class);
    $this->process = app(ProcessRevenueWithdrawalAction::class);
    $this->admin = Admin::create(['name' => 'Op', 'email' => 'fin@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $this->approver = Admin::create(['name' => 'Boss', 'email' => 'boss@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);
    $this->to = Evm::toChecksumAddress('0x'.str_repeat('33', 20)); // a valid EVM destination
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

function requestRevenue($admin, $asset, string $base, string $to)
{
    return app(RequestRevenueWithdrawalAction::class)->execute($admin, $asset, Money::ofBase($base, 6, 'USDT'), 'ethereum', $to);
}

it('reflects collected fees as the revenue balance', function () {
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000', LedgerAccountType::FeeCard);
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '500000', LedgerAccountType::FxSpreadIncome);

    expect($this->revenue->balance($this->usdt)->baseString())->toBe('1500000')
        ->and($this->revenue->stats($this->usdt)['lifetime']->baseString())->toBe('1500000');
});

it('rejects a payout address that does not match the network', function () {
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000');

    // A TRON T-address for an EVM (ethereum) asset must be rejected.
    requestRevenue($this->admin, $this->usdt, '400000', 'TRee4QxddRp4hS9BsavhxWEqKbLif8dVYe');
})->throws(InvalidWithdrawalAddressException::class);

it('records a pending withdrawal without moving money', function () {
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000');

    $w = requestRevenue($this->admin, $this->usdt, '400000', $this->to);

    expect($w->status)->toBe(RevenueWithdrawalStatus::Pending)
        ->and($this->revenue->balance($this->usdt)->baseString())->toBe('1000000');
});

it('approves: moves revenue out of the wallet and queues the broadcast', function () {
    Queue::fake();
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000');
    $w = requestRevenue($this->admin, $this->usdt, '400000', $this->to);

    $this->process->approve($w, $this->approver);

    expect($w->fresh()->status)->toBe(RevenueWithdrawalStatus::Approved)
        ->and($w->fresh()->entry_id)->not->toBeNull()
        ->and($this->revenue->balance($this->usdt)->baseString())->toBe('600000');
    Queue::assertPushed(BroadcastRevenueWithdrawalJob::class);
});

it('completes via the broadcast job with a REAL on-chain tx hash', function () {
    Queue::fake();
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000');
    $w = requestRevenue($this->admin, $this->usdt, '400000', $this->to);
    $this->process->approve($w, $this->approver);

    app()->call([new BroadcastRevenueWithdrawalJob($w->id), 'handle']);

    $fresh = $w->fresh();
    $fabricated = '0x'.substr(hash('sha256', $w->id.$w->idempotency_key), 0, 64);
    expect($fresh->status)->toBe(RevenueWithdrawalStatus::Completed)
        ->and($fresh->tx_hash)->toStartWith('0x')
        ->and($fresh->tx_hash)->not->toBe($fabricated)          // never the old simulated hash
        ->and($this->fake->sent)->toHaveCount(1);              // a real broadcast happened
});

it('marks the withdrawal Failed (never fakes) when custody is simulated', function () {
    config(['poisapay.custody_simulated' => true]);
    Queue::fake();
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000');
    $w = requestRevenue($this->admin, $this->usdt, '400000', $this->to);
    $this->process->approve($w, $this->approver);

    app()->call([new BroadcastRevenueWithdrawalJob($w->id), 'handle']);

    expect($w->fresh()->status)->toBe(RevenueWithdrawalStatus::Failed)
        ->and($w->fresh()->tx_hash)->toBeNull()
        ->and($this->revenue->balance($this->usdt)->baseString())->toBe('1000000'); // revenue returned
});

it('reverses the ledger entry when a withdrawal fails', function () {
    Queue::fake();
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000');
    $w = requestRevenue($this->admin, $this->usdt, '400000', $this->to);
    $this->process->approve($w, $this->approver);

    $this->process->markFailed($w, 'network error');

    expect($w->fresh()->status)->toBe(RevenueWithdrawalStatus::Failed)
        ->and($this->revenue->balance($this->usdt)->baseString())->toBe('1000000'); // returned
});

it('rejects a withdrawal above the revenue balance', function () {
    seedRevenue($this->ledger, $this->resolver, $this->usdt, '1000000');

    requestRevenue($this->admin, $this->usdt, '2000000', $this->to);
})->throws(RuntimeException::class);
