<?php

declare(strict_types=1);

use App\Domain\Chain\ChainHealthService;
use App\Domain\Chain\SweepDepositAction;
use App\Domain\Custody\AllocateDepositAddressAction;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Enums\SweepStatus;
use App\Models\CustodyXpub;
use App\Models\GasWallet;
use App\Models\RpcEndpoint;
use App\Models\Sweep;
use App\Models\User;
use App\Support\Money;
use Brick\Math\BigInteger;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->chain = $this->asset->chain;
    $this->ledger = app(LedgerService::class);
    CustodyXpub::create([
        'chain_id' => $this->chain->id, 'label' => 'x', 'xpub' => 'xpub-sweep',
        'derivation_path' => 'm', 'next_index' => 0, 'purpose' => 'deposit',
    ]);
    // Fund treasury:pending so there is something to sweep to hot.
    $r = app(AccountResolver::class);
    $pending = $r->system(LedgerAccountType::TreasuryPending, $this->asset->id);
    $liab = $r->system(LedgerAccountType::LiabilityUserFunds, $this->asset->id);
    $this->ledger->post(new EntryData('seed', 'seed:pending:usdt', [
        PostingLine::debit($pending->id, $this->asset->id, '10000000'),
        PostingLine::credit($liab->id, $this->asset->id, '10000000'),
    ]));
    GasWallet::create(['chain_id' => $this->chain->id, 'balance' => '5000000000000000000', 'min_threshold' => '0']);
});

it('sweeps pending funds into the hot wallet and charges gas', function () {
    $user = User::factory()->create();
    $address = app(AllocateDepositAddressAction::class)->execute($user, $this->chain);

    $sweep = app(SweepDepositAction::class)->execute($address, $this->asset, Money::ofBase('4000000', 6, 'USDT'), 'sweep:test:1');

    expect($sweep->status)->toBe(SweepStatus::Swept)
        ->and(BigInteger::of($sweep->gas_cost)->isPositive())->toBeTrue();

    // Hot balance increased (debit-normal): debit hot 4 USDT.
    $hot = app(AccountResolver::class)->system(LedgerAccountType::TreasuryHot, $this->asset->id)->fresh('balance');
    expect($hot->balance->balance)->toBe('4000000');

    // Gas wallet was debited.
    expect(GasWallet::where('chain_id', $this->chain->id)->first()->balance)->not->toBe('5000000000000000000');
});

it('is idempotent by nonce/context', function () {
    $user = User::factory()->create();
    $address = app(AllocateDepositAddressAction::class)->execute($user, $this->chain);
    $action = app(SweepDepositAction::class);

    $a = $action->execute($address, $this->asset, Money::ofBase('1000000', 6, 'USDT'), 'dup');
    $b = $action->execute($address, $this->asset, Money::ofBase('1000000', 6, 'USDT'), 'dup');

    expect($b->id)->toBe($a->id)->and(Sweep::count())->toBe(1);
});

it('probes RPC endpoints and updates health', function () {
    $rpc = RpcEndpoint::create([
        'chain_id' => $this->chain->id, 'name' => 'Primary', 'url' => 'https://rpc.test',
        'priority' => 1, 'weight' => 10, 'is_active' => true, 'status' => 'unknown',
    ]);

    $checked = app(ChainHealthService::class)->checkAll();

    expect($checked)->toBe(1)
        ->and($rpc->fresh()->status)->toBeIn(['up', 'degraded', 'down'])
        ->and($rpc->fresh()->last_checked_at)->not->toBeNull();
});

it('summarises chain health for the console', function () {
    RpcEndpoint::create(['chain_id' => $this->chain->id, 'name' => 'P', 'url' => 'https://a.test', 'priority' => 1, 'is_active' => true, 'status' => 'up']);

    $summary = app(ChainHealthService::class)->summary();
    $row = $summary->firstWhere(fn ($r) => $r['chain']->id === $this->chain->id);

    expect($row)->not->toBeNull()
        ->and($row['rpc_total'])->toBe(1)
        ->and($row['gas'])->not->toBeNull();
});
