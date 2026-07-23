<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Reconciliation\CustodyReconciler;
use App\Domain\Reconciliation\HotColdWatermarkMonitor;
use App\Domain\Reconciliation\ReconciliationService;
use Illuminate\Console\Command;

class ReconcileCommand extends Command
{
    protected $signature = 'poisapay:reconcile';

    protected $description = 'Reconcile custody: ledger solvency (treasury ≥ liability) + on-chain hot backing vs treasury:hot; alert on drift';

    public function handle(ReconciliationService $solvency, CustodyReconciler $onchain, HotColdWatermarkMonitor $watermarks): int
    {
        $this->info('— Ledger solvency (treasury ≥ liability) —');
        foreach ($solvency->runAll() as $run) {
            $status = $run->is_solvent ? 'ok' : 'INSOLVENT';
            $this->line("  asset#{$run->asset_id}: treasury={$run->ledger_treasury} liability={$run->ledger_liability} onchain={$run->onchain_controlled}  {$status}");
        }

        $this->info('— On-chain hot backing (chain vs treasury:hot) —');
        $breaches = 0;
        foreach ($onchain->reconcile() as $row) {
            if ($row['error'] !== null) {
                $this->warn("  {$row['chain']}/{$row['asset']}: {$row['error']}");

                continue;
            }

            $line = "  {$row['chain']}/{$row['asset']}: onchain={$row['onchain']} ledger={$row['ledger']} drift={$row['drift']}";
            if ($row['breached']) {
                $this->error($line.'  ⚠ DRIFT');
                $breaches++;
            } else {
                $this->info($line.'  ok');
            }
        }

        $this->line("On-chain backing: {$breaches} breach(es).");

        $this->info('— Hot-wallet watermarks —');
        foreach ($watermarks->evaluate() as $row) {
            $line = "  {$row['asset']}: hot={$row['hot']} (high={$row['high']} low={$row['low']})";
            $row['state'] === 'ok' ? $this->info("{$line}  ok") : $this->warn("{$line}  ⚠ {$row['state']}");
        }

        return self::SUCCESS;
    }
}
