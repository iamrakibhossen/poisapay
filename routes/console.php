<?php

use App\Jobs\EvmCustodyTickJob;
use App\Jobs\TronCustodyTickJob;
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

// Live TRON custody tick — scan deposits, broadcast approved TRC20 withdrawals,
// advance confirmations. No-op while custody is simulated (chain-tick handles that).
Schedule::job(new TronCustodyTickJob)->everyMinute()->withoutOverlapping();

// Live EVM (Ethereum/BSC) custody tick — no-op while custody is simulated (Wave 2).
Schedule::job(new EvmCustodyTickJob)->everyMinute()->withoutOverlapping();

// Custody reconciliation: on-chain hot-wallet balances vs treasury:hot ledger; alert on drift.
Schedule::command('poisapay:reconcile')->everyFiveMinutes()->withoutOverlapping();

// Release the reserve on failed withdrawals that never broadcast (opt-in via feature flag).
Schedule::command('poisapay:resolve-failed-withdrawals')->everyFiveMinutes()->withoutOverlapping();

// P2P marketplace: expire orders past their payment window and refund escrow.
Schedule::command('p2p:process-timeouts')->everyMinute()->withoutOverlapping();
Schedule::command('p2p:sweep-presence')->everyFiveMinutes()->withoutOverlapping();

// Sell: release held seller earnings to spendable once the refund window passes
// (no-op unless the sell_earnings_hold flag is on).
Schedule::command('poisapay:shop-release-earnings')->hourly()->withoutOverlapping();

// Sell: escalate refund requests the seller ignored past their response SLA.
Schedule::command('poisapay:shop-escalate-refunds')->hourly()->withoutOverlapping();

// Analytics: rebuild materialised daily rollups + flush the report cache (hourly).
Schedule::command('poisapay:analytics-rollup')->hourly()->withoutOverlapping()->runInBackground();

// Ops (Wave 7): nightly DB backup + weekly telemetry retention + audit-chain heartbeat.
Schedule::command('poisapay:backup')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('poisapay:retention')->weekly();
Schedule::command('poisapay:webhooks-clean')->daily()->runInBackground();
Schedule::command('poisapay:audit-verify')->dailyAt('03:00');
