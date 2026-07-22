<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Chain\SweepDepositAction;
use App\Domain\Deposit\CreditDepositAction;
use App\Domain\Withdrawal\SettleWithdrawalAction;
use App\Enums\DepositStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Simulated chain monitor tick (TDD §6). Each run advances confirmations on
 * pending deposits — crediting them once they reach the required depth — and
 * broadcasts+settles approved withdrawals. In production this work is split
 * across the Blockchain Monitor and the isolated signers; here one idempotent
 * tick drives the whole pipeline so flows move end-to-end without a live chain.
 */
class ChainTickJob implements ShouldQueue
{
    use Queueable;

    /** @param  int  $confPerTick  confirmations added to each deposit per tick */
    public function __construct(public int $confPerTick = 6) {}

    public function handle(CreditDepositAction $credit, SettleWithdrawalAction $settle, SweepDepositAction $sweep): void
    {
        // 1. Advance deposit confirmations, credit when canonical + deep enough.
        Deposit::with('onchainTx')
            ->whereIn('status', [DepositStatus::Detected->value, DepositStatus::Confirming->value])
            ->get()
            ->each(function (Deposit $deposit) use ($credit) {
                $confs = min($deposit->confirmations + $this->confPerTick, $deposit->required_confirmations);
                $deposit->update([
                    'confirmations' => $confs,
                    'status' => $confs >= $deposit->required_confirmations ? DepositStatus::Confirming : DepositStatus::Detected,
                ]);
                $deposit->onchainTx?->update(['confirmations' => $confs]);

                if ($confs >= $deposit->required_confirmations) {
                    $credit->execute($deposit->fresh());
                }
            });

        // 2. Sweep newly-credited deposits into the hot wallet (idempotent per deposit).
        Deposit::with('asset', 'depositAddress')
            ->where('status', DepositStatus::Credited->value)
            ->doesntHave('depositAddress.sweeps')  // best-effort: skip already-swept addresses
            ->limit(50)
            ->get()
            ->each(function (Deposit $deposit) use ($sweep) {
                if (! $deposit->depositAddress || ! $deposit->asset) {
                    return;
                }
                $sweep->execute(
                    $deposit->depositAddress,
                    $deposit->asset,
                    Money::ofBase($deposit->amount, $deposit->asset->decimals, $deposit->asset->symbol),
                    'sweep:deposit:'.$deposit->id,
                );
            });

        // 3. Broadcast + settle approved withdrawals (isolated signer stand-in).
        Withdrawal::where('status', WithdrawalStatus::Approved->value)
            ->get()
            ->each(fn (Withdrawal $w) => $settle->execute($w, '0x'.bin2hex(random_bytes(16))));
    }
}
