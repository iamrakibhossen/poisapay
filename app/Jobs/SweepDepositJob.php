<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Chain\Evm\EvmSweepAction;
use App\Domain\Chain\Tron\TronSweepAction;
use App\Domain\Deposit\CreditDepositAction;
use App\Events\SweepRequested;
use App\Models\Asset;
use App\Models\DepositAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sweeps a single deposit address into the hot wallet right after its deposit is credited.
 * Queued by {@see CreditDepositAction} only when auto_sweep_on_confirm
 * is on. The broadcast itself stays gated by onchain_sweep_enabled inside the sweep action,
 * so this is a safe no-op when sweeping is off. Settlement (ledger treasury:hot ← pending)
 * is handled by the periodic Settle*SweepsAction on the custody tick, after confirmation.
 */
class SweepDepositJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $depositAddressId, public int $assetId) {}

    public function handle(TronSweepAction $tronSweep, EvmSweepAction $evmSweep): void
    {
        $address = DepositAddress::find($this->depositAddressId);
        $asset = Asset::with('chain')->find($this->assetId);
        if ($address === null || $asset === null || $asset->chain === null) {
            return;
        }

        SweepRequested::dispatch($address->id, $asset->id);

        $asset->chain->is_evm
            ? $evmSweep->execute($address, $asset)
            : $tronSweep->execute($address, $asset);
    }
}
