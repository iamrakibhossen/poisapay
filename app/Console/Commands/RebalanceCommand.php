<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Chain\Evm\EvmHotColdMoveAction;
use App\Domain\Chain\Evm\SettleEvmHotColdMovesAction;
use App\Domain\Chain\Tron\SettleTronHotColdMovesAction;
use App\Domain\Chain\Tron\TronHotColdMoveAction;
use App\Domain\Treasury\RequestColdRefillAction;
use App\Domain\Treasury\SettleColdRefillAction;
use App\Models\Asset;
use Illuminate\Console\Command;

class RebalanceCommand extends Command
{
    protected $signature = 'poisapay:rebalance';

    protected $description = 'Rebalance hot↔cold: move excess to cold (over high-watermark) and request refills from cold (under low-watermark), TRON + EVM. Each direction opt-in via its own flag.';

    public function handle(
        TronHotColdMoveAction $tronMove,
        SettleTronHotColdMovesAction $tronSettle,
        EvmHotColdMoveAction $evmMove,
        SettleEvmHotColdMovesAction $evmSettle,
        RequestColdRefillAction $refill,
        SettleColdRefillAction $refillSettle,
    ): int {
        $moved = 0;
        $requested = 0;

        foreach (Asset::where('is_active', true)->whereNotNull('contract_address')->with('chain')->get() as $asset) {
            $chain = $asset->chain;
            if ($chain === null || ! $chain->is_active) {
                continue;
            }

            // Over high-watermark → move excess to cold (gated by hot_cold_move_enabled).
            $move = $chain->is_evm ? $evmMove->execute($asset) : $tronMove->execute($asset);
            if ($move !== null) {
                $moved++;
            }

            // Under low-watermark → raise a cold→hot refill request (gated by hot_cold_refill_enabled).
            if ($refill->execute($asset) !== null) {
                $requested++;
            }
        }

        $settled = $tronSettle->execute() + $evmSettle->execute() + $refillSettle->execute();
        $this->info("Moves broadcast: {$moved}, refill requests: {$requested}, settled: {$settled}.");

        return self::SUCCESS;
    }
}
