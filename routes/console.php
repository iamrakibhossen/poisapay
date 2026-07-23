<?php

use App\Jobs\EvmCustodyTickJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Simulated chain monitor: advance confirmations, credit deposits, sweep, settle withdrawals.
Schedule::command('poisapay:chain-tick')->everyMinute()->withoutOverlapping();

// Simulated node/RPC health probe.
Schedule::command('poisapay:chain-health')->everyFiveMinutes()->withoutOverlapping();

// Live EVM (Ethereum/BSC) custody tick — no-op while custody is simulated (Wave 2).
Schedule::job(new EvmCustodyTickJob)->everyMinute()->withoutOverlapping();

// P2P marketplace: expire orders past their payment window and refund escrow.
Schedule::command('p2p:process-timeouts')->everyMinute()->withoutOverlapping();

// Ops (Wave 7): nightly DB backup + weekly telemetry retention + audit-chain heartbeat.
Schedule::command('poisapay:backup')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('poisapay:retention')->weekly();
Schedule::command('poisapay:webhooks-clean')->daily()->runInBackground();
Schedule::command('poisapay:audit-verify')->dailyAt('03:00');
