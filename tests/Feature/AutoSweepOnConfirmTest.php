<?php

declare(strict_types=1);

use App\Domain\Deposit\CreditDepositAction;
use App\Enums\DepositStatus;
use App\Enums\OnchainTxStatus;
use App\Jobs\SweepDepositJob;
use App\Models\Asset;
use App\Models\CustodyXpub;
use App\Models\Deposit;
use App\Models\DepositAddress;
use App\Models\OnchainTx;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

/**
 * Delta 2: auto-sweep on confirmation. When auto_sweep_on_confirm is on, crediting a
 * deposit queues a SweepDepositJob to consolidate the deposit address into the hot wallet.
 * Default OFF preserves the operator-controlled sweep design.
 */
beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->user = User::factory()->create();
    $xpub = CustodyXpub::create([
        'chain_id' => $this->asset->chain_id, 'label' => 'tron', 'xpub' => 'xpub-test',
        'derivation_path' => "m/44'/195'/0'", 'next_index' => 1, 'purpose' => 'deposit',
    ]);
    $this->address = DepositAddress::create([
        'user_id' => $this->user->id, 'chain_id' => $this->asset->chain_id, 'xpub_id' => $xpub->id,
        'derivation_index' => 0, 'address' => 'TWatchedAddr1', 'is_watched' => true,
    ]);
});

function creditableDeposit(Asset $asset, User $user, DepositAddress $address): Deposit
{
    $tx = OnchainTx::create([
        'chain_id' => $asset->chain_id, 'tx_hash' => 'sweeptxhash', 'log_index' => 0,
        'to_address' => $address->address, 'asset_id' => $asset->id, 'amount' => '3000000',
        'block_number' => 100, 'confirmations' => 19, 'status' => OnchainTxStatus::Confirmed, 'direction' => 'in',
    ]);

    return Deposit::create([
        'user_id' => $user->id, 'deposit_address_id' => $address->id, 'asset_id' => $asset->id,
        'source' => 'onchain', 'onchain_tx_id' => $tx->id, 'amount' => '3000000',
        'confirmations' => 19, 'required_confirmations' => 19, 'status' => DepositStatus::Confirming,
    ]);
}

it('does not queue a sweep on credit when auto_sweep_on_confirm is off (default)', function () {
    Bus::fake();
    $deposit = creditableDeposit($this->asset, $this->user, $this->address);

    app(CreditDepositAction::class)->execute($deposit);

    expect($deposit->fresh()->status)->toBe(DepositStatus::Credited);
    Bus::assertNotDispatched(SweepDepositJob::class);
});

it('queues a sweep on credit when auto_sweep_on_confirm is on', function () {
    updateSetting('auto_sweep_on_confirm', true, 'features');
    Bus::fake();
    $deposit = creditableDeposit($this->asset, $this->user, $this->address);

    app(CreditDepositAction::class)->execute($deposit);

    expect($deposit->fresh()->status)->toBe(DepositStatus::Credited);
    Bus::assertDispatched(
        SweepDepositJob::class,
        fn (SweepDepositJob $job) => $job->depositAddressId === $this->address->id && $job->assetId === $this->asset->id,
    );
});
