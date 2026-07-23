<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Withdrawal\ResolveFailedWithdrawalsAction;
use Illuminate\Console\Command;

class ResolveFailedWithdrawalsCommand extends Command
{
    protected $signature = 'poisapay:resolve-failed-withdrawals';

    protected $description = 'Release the reserve on failed withdrawals that never broadcast (opt-in: withdrawal_auto_release_failed flag)';

    public function handle(ResolveFailedWithdrawalsAction $action): int
    {
        $released = $action->execute();
        $this->info("Released reserve on {$released} failed withdrawal(s).");

        return self::SUCCESS;
    }
}
