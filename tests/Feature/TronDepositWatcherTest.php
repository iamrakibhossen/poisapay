<?php

declare(strict_types=1);

use App\Domain\Chain\Tron\AdvanceTronDepositsAction;
use App\Domain\Chain\Tron\ScanTronDepositsAction;
use App\Domain\Ledger\LedgerService;
use App\Enums\DepositStatus;
use App\Models\CustodyXpub;
use App\Models\Deposit;
use App\Models\DepositAddress;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');           // tron chain, 19 confirmations
    $this->chain = $this->asset->chain;
    $this->ledger = app(LedgerService::class);
    config(['poisapay.custody.tron.usdt_contract' => $this->asset->contract_address]);

    $this->user = User::factory()->create();
    $xpub = CustodyXpub::create([
        'chain_id' => $this->chain->id, 'label' => 'tron', 'xpub' => 'xpub-test',
        'derivation_path' => "m/44'/195'/0'", 'next_index' => 1, 'purpose' => 'deposit',
    ]);
    $this->address = DepositAddress::create([
        'user_id' => $this->user->id, 'chain_id' => $this->chain->id, 'xpub_id' => $xpub->id,
        'derivation_index' => 0, 'address' => 'TWatchedAddr1', 'is_watched' => true,
    ]);
});

/** Fake TronGrid: one inbound USDT transfer, a latest block, and a tx-info block. */
function fakeTron(string $to, string $value, int $latest, ?int $block, string $result = 'SUCCESS'): void
{
    Http::fake([
        '*/v1/accounts/*/transactions/trc20*' => Http::response(['data' => [[
            'transaction_id' => 'txabc123', 'from' => 'TfromAddr', 'to' => $to, 'value' => $value,
            'token_info' => ['address' => config('poisapay.custody.tron.usdt_contract')],
        ]]]),
        '*/wallet/getnowblock' => Http::response(['block_header' => ['raw_data' => ['number' => $latest]]]),
        '*/wallet/gettransactioninfobyid' => Http::response(
            $block === null ? [] : ['blockNumber' => $block, 'receipt' => ['result' => $result]]
        ),
    ]);
}

it('detects an inbound USDT transfer as a pending deposit', function () {
    fakeTron('TWatchedAddr1', '7000000', 1000, 982);

    $count = app(ScanTronDepositsAction::class)->execute();

    expect($count)->toBe(1);
    $deposit = Deposit::where('user_id', $this->user->id)->firstOrFail();
    expect($deposit->status)->toBe(DepositStatus::Detected)
        ->and($deposit->source)->toBe('onchain')
        ->and($deposit->amount)->toBe('7000000')
        ->and($deposit->required_confirmations)->toBe(19);
});

it('does not double-record the same transfer across scans', function () {
    fakeTron('TWatchedAddr1', '7000000', 1000, 982);

    app(ScanTronDepositsAction::class)->execute();
    app(ScanTronDepositsAction::class)->execute();

    expect(Deposit::where('user_id', $this->user->id)->count())->toBe(1);
});

it('credits the deposit once it reaches the required confirmations', function () {
    fakeTron('TWatchedAddr1', '7000000', 1000, 982); // 1000-982+1 = 19 confs == required
    app(ScanTronDepositsAction::class)->execute();

    app(AdvanceTronDepositsAction::class)->execute();

    expect(Deposit::where('user_id', $this->user->id)->first()->status)->toBe(DepositStatus::Credited)
        ->and($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('7000000');
});

it('holds a shallow deposit at confirming without crediting', function () {
    fakeTron('TWatchedAddr1', '7000000', 1000, 995); // only 6 confs
    app(ScanTronDepositsAction::class)->execute();

    app(AdvanceTronDepositsAction::class)->execute();

    $deposit = Deposit::where('user_id', $this->user->id)->first();
    expect($deposit->confirmations)->toBe(6)
        ->and($deposit->status)->not->toBe(DepositStatus::Credited)
        ->and($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('0');
});

it('orphans a reverted transfer instead of crediting it', function () {
    fakeTron('TWatchedAddr1', '7000000', 1000, 982, 'REVERT');
    app(ScanTronDepositsAction::class)->execute();

    app(AdvanceTronDepositsAction::class)->execute();

    expect(Deposit::where('user_id', $this->user->id)->first()->status)->toBe(DepositStatus::Orphaned)
        ->and($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('0');
});

it('ignores transfers to a different address', function () {
    fakeTron('TSomeoneElse', '7000000', 1000, 982);

    expect(app(ScanTronDepositsAction::class)->execute())->toBe(0)
        ->and(Deposit::count())->toBe(0);
});
