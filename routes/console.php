<?php

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

// Accrue interest on active credit lines daily (§F6).
Schedule::command('poisapay:accrue-credit')->daily();
