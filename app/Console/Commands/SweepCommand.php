<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Chain\Evm\EvmSweepAction;
use App\Domain\Chain\Evm\SettleEvmSweepsAction;
use App\Domain\Chain\Tron\SettleTronSweepsAction;
use App\Domain\Chain\Tron\TronSweepAction;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\DepositAddress;
use Illuminate\Console\Command;

class SweepCommand extends Command
{
    protected $signature = 'poisapay:sweep';

    protected $description = 'Real on-chain sweep of deposit balances into the hot wallet, TRON + EVM (opt-in: onchain_sweep_enabled flag)';

    public function handle(
        TronSweepAction $tronSweep,
        SettleTronSweepsAction $tronSettle,
        EvmSweepAction $evmSweep,
        SettleEvmSweepsAction $evmSettle,
    ): int {
        if (! feature('onchain_sweep_enabled', false)) {
            $this->info('onchain_sweep_enabled is off — nothing to do.');

            return self::SUCCESS;
        }

        $broadcast = 0;
        foreach (Chain::where('is_active', true)->get() as $chain) {
            foreach (Asset::where('chain_id', $chain->id)->where('is_active', true)->whereNotNull('contract_address')->get() as $asset) {
                foreach (DepositAddress::where('chain_id', $chain->id)->where('is_watched', true)->get() as $address) {
                    $sweep = $chain->is_evm
                        ? $evmSweep->execute($address, $asset)
                        : $tronSweep->execute($address, $asset);

                    if ($sweep?->wasRecentlyCreated) {
                        $broadcast++;
                    }
                }
            }
        }

        $settled = $tronSettle->execute() + $evmSettle->execute();
        $this->info("Sweeps broadcast: {$broadcast}, settled: {$settled}.");

        return self::SUCCESS;
    }
}
