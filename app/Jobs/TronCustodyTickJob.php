<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Chain\Tron\AdvanceTronDepositsAction;
use App\Domain\Chain\Tron\ScanTronDepositsAction;
use App\Domain\Withdrawal\Tron\AdvanceTronWithdrawalsAction;
use App\Domain\Withdrawal\Tron\TronWithdrawalSigner;
use App\Enums\WithdrawalStatus;
use App\Models\Withdrawal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Real TRON custody tick (the live counterpart to the simulated ChainTickJob).
 * Drives the whole on-chain pipeline from real chain state:
 *  1. scan watched addresses for new USDT deposits
 *  2. advance + credit confirmed deposits
 *  3. sign + broadcast operator-approved withdrawals
 *  4. settle broadcast withdrawals once confirmed
 * Runs only when custody is live; a no-op under simulated custody so it can be
 * scheduled unconditionally.
 */
class TronCustodyTickJob implements ShouldQueue
{
    use Queueable;

    public function handle(
        ScanTronDepositsAction $scan,
        AdvanceTronDepositsAction $advanceDeposits,
        TronWithdrawalSigner $signer,
        AdvanceTronWithdrawalsAction $advanceWithdrawals,
    ): void {
        if (config('poisapay.custody_simulated')) {
            return;
        }

        $scan->execute();
        $advanceDeposits->execute();

        Withdrawal::where('status', WithdrawalStatus::Approved->value)
            ->whereHas('asset.chain', fn ($q) => $q->where('key', 'tron'))
            ->get()
            ->each(fn (Withdrawal $withdrawal) => $signer->execute($withdrawal));

        $advanceWithdrawals->execute();
    }
}
