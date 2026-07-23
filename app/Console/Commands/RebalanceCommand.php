<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Chain\Evm\EvmHotColdMoveAction;
use App\Domain\Chain\Evm\SettleEvmHotColdMovesAction;
use App\Domain\Chain\Tron\SettleTronHotColdMovesAction;
use App\Domain\Chain\Tron\TronHotColdMoveAction;
use App\Models\Asset;
use Illuminate\Console\Command;

class RebalanceCommand extends Command
{
    protected $signature = 'poisapay:rebalance';

    protected $description = 'Move excess hot-wallet balance to cold storage when above the high-watermark, TRON + EVM (opt-in: hot_cold_move_enabled flag)';

    public function handle(
        TronHotColdMoveAction $tronMove,
        SettleTronHotColdMovesAction $tronSettle,
        EvmHotColdMoveAction $evmMove,
        SettleEvmHotColdMovesAction $evmSettle,
    ): int {
        if (! feature('hot_cold_move_enabled', false)) {
            $this->info('hot_cold_move_enabled is off — nothing to do.');

            return self::SUCCESS;
        }

        $moved = 0;
        foreach (Asset::where('is_active', true)->whereNotNull('contract_address')->with('chain')->get() as $asset) {
            $chain = $asset->chain;
            if ($chain === null || ! $chain->is_active) {
                continue;
            }

            $move = $chain->is_evm ? $evmMove->execute($asset) : $tronMove->execute($asset);
            if ($move !== null) {
                $moved++;
            }
        }

        $settled = $tronSettle->execute() + $evmSettle->execute();
        $this->info("Moves broadcast: {$moved}, settled: {$settled}.");

        return self::SUCCESS;
    }
}
