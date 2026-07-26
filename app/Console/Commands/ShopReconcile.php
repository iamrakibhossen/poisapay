<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Shop\Services\ShopReconciler;
use Illuminate\Console\Command;

/**
 * Verifies Shop money integrity: order rows vs the ledger vs balances. Exits
 * non-zero when any CRITICAL discrepancy is found, so it doubles as a CI/monitor
 * gate. Warnings (e.g. an expected negative seller balance) do not fail the run.
 */
class ShopReconcile extends Command
{
    protected $signature = 'shop:reconcile';

    protected $description = 'Reconcile Shop orders against the ledger and account balances; flag any drift.';

    public function handle(ShopReconciler $reconciler): int
    {
        $report = $reconciler->run();
        $this->info("— Shop reconciliation ({$report['stats']['orders']} orders, {$report['stats']['assets']} asset(s)) —");

        $critical = 0;
        foreach ($report['issues'] as $issue) {
            $line = "  [{$issue['code']}] {$issue['subject']}: {$issue['detail']}";
            if ($issue['severity'] === 'critical') {
                $this->error($line);
                $critical++;
            } else {
                $this->warn($line);
            }
        }

        if ($report['issues'] === []) {
            $this->info('  ✓ clean — ledger and orders agree.');
        }

        $this->line($critical > 0 ? "{$critical} critical discrepancy(ies)." : 'No critical discrepancies.');

        return $critical > 0 ? self::FAILURE : self::SUCCESS;
    }
}
