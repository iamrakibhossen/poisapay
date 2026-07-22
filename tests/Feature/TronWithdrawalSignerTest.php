<?php

declare(strict_types=1);

use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\Crypto\Bip32;
use App\Domain\Custody\Crypto\Secp256k1Signer;
use App\Domain\Custody\TronAddressDeriver;
use App\Domain\Ledger\LedgerService;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Domain\Withdrawal\Tron\AdvanceTronWithdrawalsAction;
use App\Domain\Withdrawal\Tron\TronWithdrawalSigner;
use App\Enums\ChainType;
use App\Enums\KycTier;
use App\Enums\WithdrawalStatus;
use App\Models\OnchainTx;
use App\Models\User;
use App\Support\Money;
use Elliptic\EC;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    config([
        'poisapay.custody_simulated' => false,
        'poisapay.custody.seed' => '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f',
        'poisapay.custody.tron.usdt_contract' => $this->asset->contract_address,
    ]);

    $this->user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    $this->user->forceFill(['created_at' => now()->subMonth()])->save();
    creditUser($this->user, $this->asset, '5000000');

    // A valid TRON destination address (derived, so TronAddress::decode passes).
    $xpub = app(SignerKeyProvider::class)->accountXpub(ChainType::Tron);
    $this->destination = (new TronAddressDeriver(new Bip32))->derive(ChainType::Tron, $xpub, 99);

    $this->withdrawal = app(RequestWithdrawalAction::class)->execute(
        $this->user, $this->asset, Money::ofBase('2000000', 6, 'USDT'), $this->destination, 'wd:tron:1'
    );
    $this->withdrawal->update(['status' => WithdrawalStatus::Approved]); // operator approval
});

function fakeTronWithdrawal(int $latest, ?int $block, string $result = 'SUCCESS', bool $broadcastOk = true): void
{
    Http::fake([
        '*/wallet/triggersmartcontract' => Http::response([
            'result' => ['result' => true],
            'transaction' => ['txID' => 'a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4', 'raw_data' => ['contract' => []], 'raw_data_hex' => '0a02'],
        ]),
        '*/wallet/broadcasttransaction' => Http::response(['result' => $broadcastOk, 'txid' => 'a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4']),
        '*/wallet/getnowblock' => Http::response(['block_header' => ['raw_data' => ['number' => $latest]]]),
        '*/wallet/gettransactioninfobyid' => Http::response(
            $block === null ? [] : ['blockNumber' => $block, 'receipt' => ['result' => $result]]
        ),
    ]);
}

it('produces a valid secp256k1 signature over a hash', function () {
    $signer = new Secp256k1Signer;
    $keys = app(SignerKeyProvider::class);
    $priv = $keys->hotWalletPrivateKey(ChainType::Tron);
    $pub = (new EC('secp256k1'))->keyFromPrivate($priv)->getPublic(false, 'hex');

    $hash = hash('sha256', 'tron-tx', false);
    $sig = $signer->sign($hash, $priv);

    expect(strlen($sig))->toBe(130)                      // 65 bytes hex
        ->and($signer->verify($hash, $sig, $pub))->toBeTrue();
});

it('signs and broadcasts an approved withdrawal', function () {
    fakeTronWithdrawal(1000, 982);

    app(TronWithdrawalSigner::class)->execute($this->withdrawal);

    $w = $this->withdrawal->fresh();
    expect($w->status)->toBe(WithdrawalStatus::Broadcast)
        ->and($w->onchain_tx_id)->not->toBeNull();

    $tx = OnchainTx::find($w->onchain_tx_id);
    expect($tx->direction)->toBe('out')->and($tx->tx_hash)->toBe('a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4a1b2c3d4')->and($tx->amount)->toBe('2000000');
});

it('settles the withdrawal once the broadcast tx confirms', function () {
    fakeTronWithdrawal(1000, 982); // 19 confirmations == required
    app(TronWithdrawalSigner::class)->execute($this->withdrawal);

    app(AdvanceTronWithdrawalsAction::class)->execute();

    expect($this->withdrawal->fresh()->status)->toBe(WithdrawalStatus::Completed)
        ->and($this->ledger->lockedBalance($this->user, $this->asset->id)->baseString())->toBe('0');
});

it('marks the withdrawal failed when the broadcast is rejected', function () {
    fakeTronWithdrawal(1000, 982, broadcastOk: false);

    app(TronWithdrawalSigner::class)->execute($this->withdrawal);

    expect($this->withdrawal->fresh()->status)->toBe(WithdrawalStatus::Failed);
    // Funds stay locked (never released on a failed broadcast) for reconciliation.
    expect($this->ledger->lockedBalance($this->user, $this->asset->id)->isPositive())->toBeTrue();
});

it('fails a withdrawal whose on-chain transfer reverts', function () {
    fakeTronWithdrawal(1000, 982, result: 'REVERT');
    app(TronWithdrawalSigner::class)->execute($this->withdrawal);

    app(AdvanceTronWithdrawalsAction::class)->execute();

    expect($this->withdrawal->fresh()->status)->toBe(WithdrawalStatus::Failed)
        ->and($this->ledger->lockedBalance($this->user, $this->asset->id)->isPositive())->toBeTrue();
});
