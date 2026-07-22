<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Chain\ChainHealthService;
use Illuminate\Console\Command;

class ChainHealthCommand extends Command
{
    protected $signature = 'poisapay:chain-health';

    protected $description = 'Probe RPC endpoints and refresh node/block health (simulated)';

    public function handle(ChainHealthService $health): int
    {
        $n = $health->checkAll();
        $this->info("Checked {$n} RPC endpoint(s).");

        return self::SUCCESS;
    }
}
