<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Custody\CustodyReadiness;
use App\Domain\Revenue\ProcessRevenueWithdrawalAction;
use App\Domain\Revenue\RevenueWithdrawalBroadcaster;
use App\Enums\ChainType;
use App\Enums\RevenueWithdrawalStatus;
use App\Models\RevenueWithdrawal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Broadcast an approved revenue withdrawal to the chain. Live custody only:
 * it requires a ready signer/hot wallet, funded gas, and a reachable RPC
 * ({@see CustodyReadiness}), then broadcasts via {@see RevenueWithdrawalBroadcaster}
 * and stamps the REAL tx hash. It NEVER fabricates a hash — if custody isn't ready
 * (or is simulated), the withdrawal is marked Failed, which reverses the ledger
 * entry so the revenue returns to the wallet.
 */
class BroadcastRevenueWithdrawalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $withdrawalId) {}

    public function handle(
        ProcessRevenueWithdrawalAction $process,
        RevenueWithdrawalBroadcaster $broadcaster,
        CustodyReadiness $readiness,
    ): void {
        $withdrawal = RevenueWithdrawal::with('asset.chain')->find($this->withdrawalId);
        if (! $withdrawal || $withdrawal->status !== RevenueWithdrawalStatus::Approved) {
            return;
        }

        $process->setStatus($withdrawal, RevenueWithdrawalStatus::Broadcasting);

        try {
            $chain = $withdrawal->asset->chain?->key; // Chain::$key is cast to ChainType
            if ($chain === null) {
                throw new RuntimeException('Revenue withdrawal asset is not on a broadcastable chain.');
            }

            // Live custody + readiness are mandatory. No fallback, no fake hash.
            $readiness->assertReady($chain);

            $process->setStatus($withdrawal, RevenueWithdrawalStatus::Processing);
            $result = $broadcaster->broadcast($withdrawal);

            $process->markCompleted($withdrawal, $result['tx_hash'], $result['gas']);
        } catch (Throwable $e) {
            $process->markFailed($withdrawal, $e->getMessage());
        }
    }

    public function failed(Throwable $e): void
    {
        app(ProcessRevenueWithdrawalAction::class)->markFailed(
            RevenueWithdrawal::findOrFail($this->withdrawalId),
            'Broadcast job failed: '.$e->getMessage(),
        );
    }
}
