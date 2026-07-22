<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Chain\SimulateInboundDepositAction;
use App\Domain\Custody\AllocateDepositAddressAction;
use App\Models\Asset;
use App\Models\User;
use App\Support\Money;
use Illuminate\Console\Command;

class SimulateDepositCommand extends Command
{
    protected $signature = 'poisapay:simulate-deposit {email} {asset} {amount}';

    protected $description = 'Simulate an inbound on-chain deposit for a user (detection only; credited by chain-tick)';

    public function handle(AllocateDepositAddressAction $allocate, SimulateInboundDepositAction $simulate): int
    {
        $user = User::where('email', $this->argument('email'))->first();
        $asset = Asset::where('symbol', $this->argument('asset'))->whereNotNull('chain_id')->first();

        if (! $user || ! $asset) {
            $this->error('Unknown user or crypto asset.');

            return self::FAILURE;
        }

        $address = $allocate->execute($user, $asset->chain);
        $amount = Money::ofDecimal($this->argument('amount'), $asset->decimals, $asset->symbol);
        $deposit = $simulate->execute($address, $asset, $amount);

        $this->info("Detected {$amount->format()} to {$address->address} (deposit {$deposit->id}). Run poisapay:chain-tick to confirm & credit.");

        return self::SUCCESS;
    }
}
