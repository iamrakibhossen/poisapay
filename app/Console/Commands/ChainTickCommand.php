<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Chain\SweepDepositAction;
use App\Domain\Deposit\CreditDepositAction;
use App\Domain\Withdrawal\SettleWithdrawalAction;
use App\Jobs\ChainTickJob;
use Illuminate\Console\Command;

class ChainTickCommand extends Command
{
    protected $signature = 'poisapay:chain-tick {--confs=6 : Confirmations to add per tick}';

    protected $description = 'Advance simulated confirmations, credit deposits, and settle approved withdrawals';

    public function handle(): int
    {
        (new ChainTickJob((int) $this->option('confs')))->handle(
            app(CreditDepositAction::class),
            app(SettleWithdrawalAction::class),
            app(SweepDepositAction::class),
        );

        $this->info('Chain tick complete.');

        return self::SUCCESS;
    }
}
