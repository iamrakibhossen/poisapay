<?php

declare(strict_types=1);

use App\Enums\GasWalletHealth;
use App\Models\GasWallet;

// Native-coin base units (18 decimals). Thresholds mirror the ETH production values:
//   warning = 0.02, critical = 0.005.
const WARN = '20000000000000000';   // 0.02
const CRIT = '5000000000000000';    // 0.005

beforeEach(function () {
    $this->chain = testAsset('USDT', 6, 'tron')->chain;
});

function makeGasWallet(string $balance, string $warning = WARN, string $critical = CRIT): GasWallet
{
    return GasWallet::create([
        'chain_id' => test()->chain->id,
        'address' => '0x'.str_repeat('1', 40),
        'balance' => $balance,
        'min_threshold' => $warning,
        'critical_threshold' => $critical,
        'is_active' => true,
    ]);
}

it('is Healthy at or above the warning threshold', function () {
    $w = makeGasWallet('30000000000000000'); // 0.03 >= 0.02
    expect($w->health())->toBe(GasWalletHealth::Healthy)
        ->and($w->isLow())->toBeFalse()
        ->and($w->isCritical())->toBeFalse()
        ->and($w->canPayGas())->toBeTrue();
});

it('is Warning below the warning threshold but above critical — alerts but does not block', function () {
    $w = makeGasWallet('10000000000000000'); // 0.01: < 0.02 warn, > 0.005 crit
    expect($w->health())->toBe(GasWalletHealth::Warning)
        ->and($w->isLow())->toBeTrue()        // backwards-compatible "low" still true
        ->and($w->isCritical())->toBeFalse()
        ->and($w->canPayGas())->toBeTrue();    // WARNING must NOT block withdrawals
});

it('is Critical below the critical threshold and blocks withdrawals', function () {
    $w = makeGasWallet('1000000000000000'); // 0.001 < 0.005 crit
    expect($w->health())->toBe(GasWalletHealth::Critical)
        ->and($w->isLow())->toBeTrue()
        ->and($w->isCritical())->toBeTrue()
        ->and($w->canPayGas())->toBeFalse();   // CRITICAL blocks
});

it('keeps warning-only, non-blocking behaviour when no critical threshold is set', function () {
    // critical_threshold = 0 → legacy single-tier: never critical even at zero balance.
    $w = makeGasWallet('0', WARN, '0');
    expect($w->health())->toBe(GasWalletHealth::Warning)
        ->and($w->isLow())->toBeTrue()
        ->and($w->isCritical())->toBeFalse()
        ->and($w->canPayGas())->toBeTrue();
});

it('only Critical blocks withdrawals at the enum level', function () {
    expect(GasWalletHealth::Healthy->blocksWithdrawals())->toBeFalse()
        ->and(GasWalletHealth::Warning->blocksWithdrawals())->toBeFalse()
        ->and(GasWalletHealth::Critical->blocksWithdrawals())->toBeTrue()
        ->and(GasWalletHealth::Healthy->isAlertable())->toBeFalse()
        ->and(GasWalletHealth::Warning->isAlertable())->toBeTrue()
        ->and(GasWalletHealth::Critical->isAlertable())->toBeTrue();
});
