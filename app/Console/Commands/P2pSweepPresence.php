<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\P2p\P2pPresenceService;
use Illuminate\Console\Command;

class P2pSweepPresence extends Command
{
    protected $signature = 'p2p:sweep-presence';

    protected $description = 'Flip P2P merchants offline after a period of inactivity.';

    public function handle(P2pPresenceService $presence): int
    {
        $count = $presence->sweepOffline();
        $this->info("Set {$count} merchant(s) offline.");

        return self::SUCCESS;
    }
}
