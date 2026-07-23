<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Chain\Tron\SettleTronSweepsAction;
use App\Domain\Chain\Tron\TronSweepAction;
use App\Enums\ChainType;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\DepositAddress;
use Illuminate\Console\Command;

class SweepCommand extends Command
{
    protected $signature = 'poisapay:sweep';

    protected $description = 'Real on-chain sweep of TRON deposit balances into the hot wallet (opt-in: onchain_sweep_enabled flag)';

    public function handle(TronSweepAction $sweep, SettleTronSweepsAction $settle): int
    {
        if (! feature('onchain_sweep_enabled', false)) {
            $this->info('onchain_sweep_enabled is off — nothing to do.');

            return self::SUCCESS;
        }

        $tron = Chain::where('key', ChainType::Tron->value)->where('is_active', true)->first();
        if ($tron === null) {
            $this->warn('No active TRON chain.');

            return self::SUCCESS;
        }

        $broadcast = 0;
        foreach (Asset::where('chain_id', $tron->id)->where('is_active', true)->whereNotNull('contract_address')->get() as $asset) {
            foreach (DepositAddress::where('chain_id', $tron->id)->where('is_watched', true)->get() as $address) {
                if ($sweep->execute($address, $asset)?->wasRecentlyCreated) {
                    $broadcast++;
                }
            }
        }

        $settled = $settle->execute();
        $this->info("Sweeps broadcast: {$broadcast}, settled: {$settled}.");

        return self::SUCCESS;
    }
}
