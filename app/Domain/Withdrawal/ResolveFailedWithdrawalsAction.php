<?php

declare(strict_types=1);

namespace App\Domain\Withdrawal;

use App\Domain\Audit\ActivityLogger;
use App\Domain\Ledger\LedgerService;
use App\Enums\WithdrawalStatus;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Releases the reserve (locked → available) on failed withdrawals that were
 * DEFINITIVELY never broadcast — i.e. the signer rejected them before any
 * on-chain transaction existed (`onchain_tx_id` is null). Those funds would
 * otherwise stay locked forever.
 *
 * Post-broadcast failures (dropped / reverted, which carry an onchain_tx) are
 * intentionally left locked here: they are ambiguous and belong to the
 * reconciliation / manual-review path, preserving the existing safe default.
 *
 * Opt-in and backward-compatible: does nothing unless the
 * `withdrawal_auto_release_failed` feature flag is enabled (default off), so
 * current behaviour is unchanged until an operator turns it on. The release is
 * idempotent (keyed ledger entry + a reserve_released_at marker).
 */
class ResolveFailedWithdrawalsAction
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function execute(): int
    {
        if (! feature('withdrawal_auto_release_failed', false)) {
            return 0;
        }

        $released = 0;

        Withdrawal::query()
            ->with('asset')
            ->where('status', WithdrawalStatus::Failed->value)
            ->whereNotNull('lock_entry_id')   // a reserve was actually placed
            ->whereNull('onchain_tx_id')       // never broadcast → no on-chain movement possible
            ->whereNull('reserve_released_at') // not already released
            ->get()
            ->each(function (Withdrawal $withdrawal) use (&$released) {
                $total = Money::ofBase($withdrawal->amount, $withdrawal->asset->decimals, $withdrawal->asset->symbol)
                    ->plus(Money::ofBase($withdrawal->fee, $withdrawal->asset->decimals, $withdrawal->asset->symbol));

                DB::transaction(function () use ($withdrawal, $total) {
                    $this->ledger->unlock(
                        $withdrawal->user_id,
                        $withdrawal->asset_id,
                        $total,
                        "withdrawal:release:{$withdrawal->id}",
                        'withdrawal.failed.release',
                        ['withdrawal_id' => $withdrawal->id],
                    );

                    $withdrawal->update(['reserve_released_at' => now()]);
                });

                ActivityLogger::log(
                    'withdrawal.reserve.released',
                    $withdrawal,
                    ['amount' => $total->baseString()],
                    'Reserve released for a failed withdrawal that never broadcast',
                );

                $released++;
            });

        return $released;
    }
}
