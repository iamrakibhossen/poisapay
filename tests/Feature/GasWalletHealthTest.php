<?php

declare(strict_types=1);

use App\Enums\GasWalletHealth;
use App\Models\GasWallet;

// Native-coin base units (18 decimals). Mirrors the ETH production thresholds:
//   critical 0.002, warning 0.005, healthy 0.01.
const CRIT = '2000000000000000';     // 0.002
const WARN = '5000000000000000';     // 0.005
const HEALTHY = '10000000000000000'; // 0.01

beforeEach(function () {
    $this->chain = testAsset('USDT', 6, 'tron')->chain;
});

function makeGasWallet(string $balance, string $critical = CRIT, string $warning = WARN, string $healthy = HEALTHY): GasWallet
{
    // One gas wallet per chain (unique chain_id) — update the single row each call.
    return GasWallet::updateOrCreate(
        ['chain_id' => test()->chain->id],
        [
            'address' => '0x'.str_repeat('1', 40),
            'balance' => $balance,
            'min_threshold' => $warning,
            'critical_threshold' => $critical,
            'healthy_threshold' => $healthy,
            'is_active' => true,
        ],
    );
}

it('is Healthy at or above the healthy threshold', function () {
    $w = makeGasWallet('20000000000000000'); // 0.02 >= 0.01 healthy
    expect($w->health())->toBe(GasWalletHealth::Healthy)
        ->and($w->canPayGas())->toBeTrue();

    // Acceptance boundary: exactly 0.01 ETH is Healthy.
    expect(makeGasWallet(HEALTHY)->health())->toBe(GasWalletHealth::Healthy);
});

it('is Warning below healthy but at/above the warning marker — alerts, does not block', function () {
    $w = makeGasWallet('7000000000000000'); // 0.007: < 0.01 healthy, > 0.005 warning
    expect($w->health())->toBe(GasWalletHealth::Warning)
        ->and($w->isCritical())->toBeFalse()
        ->and($w->canPayGas())->toBeTrue();   // WARNING must NOT block
});

it('is Warning (escalated) below the warning marker but above critical — still not blocked', function () {
    $w = makeGasWallet('3000000000000000'); // 0.003: < 0.005 warning, > 0.002 critical
    expect($w->health())->toBe(GasWalletHealth::Warning)
        ->and($w->isLow())->toBeTrue()         // below the warning marker
        ->and($w->isCritical())->toBeFalse()
        ->and($w->canPayGas())->toBeTrue();    // still pays gas — only critical blocks
});

it('is Critical below the critical threshold and blocks withdrawals', function () {
    $w = makeGasWallet('1000000000000000'); // 0.001 < 0.002 critical
    expect($w->health())->toBe(GasWalletHealth::Critical)
        ->and($w->isCritical())->toBeTrue()
        ->and($w->canPayGas())->toBeFalse();   // CRITICAL blocks
});

it('blocks withdrawals ONLY below the critical threshold', function () {
    expect(makeGasWallet('2000000000000000')->canPayGas())->toBeTrue()   // exactly critical → not below → allowed
        ->and(makeGasWallet('1999999999999999')->canPayGas())->toBeFalse(); // one wei under → blocked
});

it('falls back to warning as the healthy line when no healthy target is set (backwards compatible)', function () {
    // healthy_threshold = 0, critical = 0 → legacy two-tier: Healthy at >= warning, never critical.
    expect(makeGasWallet('7000000000000000', '0', WARN, '0')->health())->toBe(GasWalletHealth::Healthy) // 0.007 >= 0.005 warn
        ->and(makeGasWallet('3000000000000000', '0', WARN, '0')->health())->toBe(GasWalletHealth::Warning) // 0.003 < 0.005
        ->and(makeGasWallet('0', '0', WARN, '0')->canPayGas())->toBeTrue(); // no critical tier → never blocks
});

it('only Critical blocks / all non-healthy alert at the enum level', function () {
    expect(GasWalletHealth::Healthy->blocksWithdrawals())->toBeFalse()
        ->and(GasWalletHealth::Warning->blocksWithdrawals())->toBeFalse()
        ->and(GasWalletHealth::Critical->blocksWithdrawals())->toBeTrue()
        ->and(GasWalletHealth::Healthy->isAlertable())->toBeFalse()
        ->and(GasWalletHealth::Warning->isAlertable())->toBeTrue()
        ->and(GasWalletHealth::Critical->isAlertable())->toBeTrue();
});
