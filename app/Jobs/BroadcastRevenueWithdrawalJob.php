<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Revenue\ProcessRevenueWithdrawalAction;
use App\Enums\RevenueWithdrawalStatus;
use App\Models\RevenueWithdrawal;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast an approved revenue withdrawal to the chain (§Finance workflow).
 * Custody is simulated, so this stamps a simulated tx hash + gas and completes.
 * When real custody is wired, the signer/broadcaster slots in here; a thrown
 * error routes the withdrawal to Failed (which reverses the ledger entry).
 */
class BroadcastRevenueWithdrawalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $withdrawalId) {}

    public function handle(ProcessRevenueWithdrawalAction $process): void
    {
        $withdrawal = RevenueWithdrawal::with('asset.chain')->find($this->withdrawalId);
        if (! $withdrawal || $withdrawal->status !== RevenueWithdrawalStatus::Approved) {
            return;
        }

        $process->setStatus($withdrawal, RevenueWithdrawalStatus::Broadcasting);

        try {
            // --- Real signer/broadcaster would run here (custody-live). ---
            $process->setStatus($withdrawal, RevenueWithdrawalStatus::Processing);

            $txHash = '0x'.substr(hash('sha256', $withdrawal->id.$withdrawal->idempotency_key), 0, 64);
            $gas = Money::ofBase('300000000000000', 18, $withdrawal->asset->chain?->native_symbol ?? 'ETH'); // ~0.0003 native

            $process->markCompleted($withdrawal, $txHash, $gas);
        } catch (\Throwable $e) {
            $process->markFailed($withdrawal, $e->getMessage());
        }
    }

    public function failed(\Throwable $e): void
    {
        app(ProcessRevenueWithdrawalAction::class)->markFailed(
            RevenueWithdrawal::findOrFail($this->withdrawalId),
            'Broadcast job failed: '.$e->getMessage(),
        );
    }
}
