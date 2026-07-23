<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Chain\Evm\AdvanceEvmDepositsAction;
use App\Domain\Chain\Evm\Contracts\BlockchainProvider;
use App\Domain\Chain\Evm\HotWalletManager;
use App\Domain\Chain\Evm\ScanEvmDepositsAction;
use App\Domain\Withdrawal\Evm\AdvanceEvmWithdrawalsAction;
use App\Domain\Withdrawal\Evm\EvmWithdrawalSigner;
use App\Domain\Withdrawal\Evm\RebroadcastStuckWithdrawalsAction;
use App\Enums\ChainType;
use App\Enums\WithdrawalStatus;
use App\Models\Chain;
use App\Models\RpcEndpoint;
use App\Models\Withdrawal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * EVM custody tick (Wave 2, live mode — mirrors TronCustodyTickJob). For each active
 * EVM chain: detect deposits, advance/credit confirmations, sync gas + RPC health,
 * sign+broadcast approved withdrawals, and advance/settle them. No-op while custody
 * is simulated. Retries with backoff so a transient RPC blip self-heals.
 */
class EvmCustodyTickJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 120];
    }

    public function handle(
        BlockchainProvider $provider,
        ScanEvmDepositsAction $scan,
        AdvanceEvmDepositsAction $advanceDeposits,
        EvmWithdrawalSigner $signer,
        AdvanceEvmWithdrawalsAction $advanceWithdrawals,
        HotWalletManager $hotWallet,
        RebroadcastStuckWithdrawalsAction $rbf,
    ): void {
        if (config('poisapay.custody_simulated')) {
            return; // live-custody only
        }

        foreach (Chain::where('is_evm', true)->where('is_active', true)->get() as $chain) {
            $chainType = $chain->key;

            $scan->execute($chainType);
            $advanceDeposits->execute($chainType);
            $hotWallet->syncGas($chainType);
            $this->syncHealth($provider, $chain, $chainType);

            Withdrawal::where('status', WithdrawalStatus::Approved)
                ->whereHas('asset', fn ($q) => $q->where('chain_id', $chain->id))
                ->get()
                ->each(fn (Withdrawal $w) => $signer->execute($w));

            $advanceWithdrawals->execute($chainType);
        }

        // Replace-By-Fee + dead-letter for stuck broadcasts (no-op unless
        // withdrawal_batching_enabled). Runs once across all chains.
        $rbf->execute();
    }

    private function syncHealth(BlockchainProvider $provider, Chain $chain, ChainType $chainType): void
    {
        try {
            $head = $provider->blockNumber($chainType);
            RpcEndpoint::where('chain_id', $chain->id)->update([
                'last_block' => $head,
                'status' => $head > 0 ? 'up' : 'unknown',
                'last_checked_at' => now(),
            ]);
        } catch (Throwable $e) {
            RpcEndpoint::where('chain_id', $chain->id)->update(['status' => 'down', 'last_checked_at' => now()]);
        }
    }
}
