<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Credit\AccrueInterestAction;
use App\Domain\Credit\CreditService;
use App\Domain\Credit\LiquidateCreditLineAction;
use App\Enums\CreditStatus;
use App\Models\CreditLine;
use Illuminate\Console\Command;

class AccrueCreditCommand extends Command
{
    protected $signature = 'poisapay:accrue-credit';

    protected $description = 'Accrue interest on active credit lines and liquidate any breaching the maintenance LTV';

    public function handle(AccrueInterestAction $accrue, CreditService $credit, LiquidateCreditLineAction $liquidate): int
    {
        $liquidated = 0;
        $accrued = 0;

        CreditLine::where('status', CreditStatus::Active->value)->get()->each(function (CreditLine $line) use ($accrue, $credit, $liquidate, &$accrued, &$liquidated) {
            $accrue->execute($line);
            $accrued++;

            if ($credit->needsLiquidation($line->refresh())) {
                $liquidate->execute($line);
                $liquidated++;
            }
        });

        $this->info("Accrued {$accrued} line(s); liquidated {$liquidated}.");

        return self::SUCCESS;
    }
}
