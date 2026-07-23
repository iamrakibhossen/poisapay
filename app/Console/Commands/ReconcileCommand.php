<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Reconciliation\CustodyReconciler;
use Illuminate\Console\Command;

class ReconcileCommand extends Command
{
    protected $signature = 'poisapay:reconcile';

    protected $description = 'Reconcile on-chain hot-wallet balances against the treasury:hot ledger; alert operators on drift';

    public function handle(CustodyReconciler $reconciler): int
    {
        $report = $reconciler->reconcile();
        $breaches = 0;

        foreach ($report as $row) {
            if ($row['error'] !== null) {
                $this->warn("{$row['chain']}/{$row['asset']}: ERROR {$row['error']}");

                continue;
            }

            $line = "{$row['chain']}/{$row['asset']}: onchain={$row['onchain']} ledger={$row['ledger']} drift={$row['drift']}";

            if ($row['breached']) {
                $this->error($line.'  ⚠ DRIFT');
                $breaches++;
            } else {
                $this->info($line.'  ok');
            }
        }

        $this->line(count($report).' account(s) checked, '.$breaches.' breach(es).');

        return self::SUCCESS;
    }
}
