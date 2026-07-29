<?php

declare(strict_types=1);

use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\Evm;
use App\Domain\Custody\Contracts\SignerKeyProvider;
use App\Domain\Custody\CustodyMode;
use App\Domain\Custody\CustodyReadiness;
use App\Domain\Custody\Exceptions\CustodyMisconfiguredException;
use App\Domain\Custody\Exceptions\CustodyNotReadyException;
use App\Domain\Withdrawal\Exceptions\InvalidWithdrawalAddressException;
use App\Domain\Withdrawal\WithdrawalAddressValidator;
use App\Enums\ChainType;
use App\Models\Chain;
use App\Models\GasWallet;

/* ── Custody mode consistency ── */

it('reports live/simulated from the config source of truth', function () {
    config(['poisapay.custody_simulated' => false]);
    expect(CustodyMode::isLive())->toBeTrue()->and(CustodyMode::isSimulated())->toBeFalse();

    config(['poisapay.custody_simulated' => true]);
    expect(CustodyMode::isSimulated())->toBeTrue()->and(CustodyMode::isLive())->toBeFalse();
});

it('passes the consistency check when the setting is unset or matches config', function () {
    config(['poisapay.custody_simulated' => false]);
    CustodyMode::assertConsistent();                 // no setting → ok
    updateSetting('custody_simulated', false, 'custody');
    CustodyMode::assertConsistent();                 // setting matches config → ok
    expect(true)->toBeTrue();
});

it('fails fast when the custody setting contradicts the config', function () {
    config(['poisapay.custody_simulated' => false]); // LIVE
    updateSetting('custody_simulated', true, 'custody'); // says SIMULATED
    CustodyMode::assertConsistent();
})->throws(CustodyMisconfiguredException::class);

/* ── Network address validation ── */

it('validates TRON destinations against a TRON asset', function () {
    $usdt = testAsset('USDT', 6, 'tron');
    $v = app(WithdrawalAddressValidator::class);

    $v->validate($usdt, 'TRee4QxddRp4hS9BsavhxWEqKbLif8dVYe'); // valid T-address → ok
    // Cross-network (an EVM 0x address to a TRON asset) is rejected.
    expect(fn () => $v->validate($usdt, '0x'.str_repeat('33', 20)))->toThrow(InvalidWithdrawalAddressException::class);
    expect(fn () => $v->validate($usdt, ''))->toThrow(InvalidWithdrawalAddressException::class);
});

it('validates EVM destinations against an EVM asset', function () {
    $usdc = testAsset('USDC', 2, 'ethereum');
    $v = app(WithdrawalAddressValidator::class);

    $v->validate($usdc, Evm::toChecksumAddress('0x'.str_repeat('33', 20))); // valid 0x → ok
    expect(fn () => $v->validate($usdc, 'TRee4QxddRp4hS9BsavhxWEqKbLif8dVYe'))->toThrow(InvalidWithdrawalAddressException::class);
    expect(fn () => $v->validate($usdc, '0xnothex'))->toThrow(InvalidWithdrawalAddressException::class);
});

it('skips address validation for fiat (off-chain) assets', function () {
    $usd = fiatAsset('USD', 2);
    app(WithdrawalAddressValidator::class)->validate($usd, 'any-bank-account-ref');
    expect(true)->toBeTrue();
});

/* ── Custody readiness ── */

it('refuses withdrawals while custody is simulated', function () {
    config(['poisapay.custody_simulated' => true]);

    expect(app(CustodyReadiness::class)->check(ChainType::Ethereum))->not->toBeEmpty();
    expect(fn () => app(CustodyReadiness::class)->assertReady(ChainType::Ethereum))
        ->toThrow(CustodyNotReadyException::class);
});

it('refuses live withdrawals when no gas wallet is configured', function () {
    liveEvm();
    Chain::create(['key' => 'ethereum', 'name' => 'Ethereum', 'native_symbol' => 'ETH', 'min_confirmations' => 12, 'is_evm' => true, 'is_active' => true]);

    $problems = app(CustodyReadiness::class)->check(ChainType::Ethereum);
    expect(collect($problems)->contains(fn ($p) => str_contains($p, 'gas wallet')))->toBeTrue();
});

it('is ready in live mode with a signer, funded gas wallet, and reachable RPC', function () {
    $fake = liveEvm();
    $fake->setBlock(ChainType::Ethereum, 100);
    $chain = Chain::create(['key' => 'ethereum', 'name' => 'Ethereum', 'native_symbol' => 'ETH', 'min_confirmations' => 12, 'is_evm' => true, 'is_active' => true]);
    $hot = app(SignerKeyProvider::class)->hotWalletAddress(ChainType::Ethereum);
    GasWallet::create([
        'chain_id' => $chain->id, 'address' => $hot, 'balance' => '1000000000000000000',
        'min_threshold' => '0', 'critical_threshold' => '0', 'healthy_threshold' => '0', 'is_active' => true,
    ]);

    expect(app(CustodyReadiness::class)->check(ChainType::Ethereum))->toBe([]);
    expect(app(CustodyReadiness::class)->isReady(ChainType::Ethereum))->toBeTrue();
});

/** Put custody into live EVM mode with the in-memory chain; returns the fake provider. */
function liveEvm(): App\Domain\Chain\Evm\FakeBlockchainProvider
{
    config([
        'poisapay.custody_simulated' => false,
        'poisapay.custody.seed' => str_repeat('a1', 32),
        'providers.blockchain.driver' => 'fake',
    ]);
    app()->forgetInstance(BlockchainProvider::class);

    return app(BlockchainProvider::class);
}
