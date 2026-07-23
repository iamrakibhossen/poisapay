<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Security\AuditChain;
use Illuminate\Console\Command;

/**
 * Verify the tamper-evident audit hash chain (Wave 4). Exit code 1 if the chain
 * is broken (a row was altered or deleted), so it can gate CI / scheduled checks.
 */
class VerifyAuditChainCommand extends Command
{
    protected $signature = 'poisapay:audit-verify';

    protected $description = 'Verify the integrity of the tamper-evident audit log hash chain';

    public function handle(): int
    {
        $result = AuditChain::verify();

        if ($result['ok']) {
            $this->info("Audit chain OK — {$result['count']} entries verified.");

            return self::SUCCESS;
        }

        $this->error("Audit chain BROKEN at sequence {$result['brokenAt']} ({$result['count']} entries scanned).");

        return self::FAILURE;
    }
}
