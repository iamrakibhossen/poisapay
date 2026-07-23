<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LoginHistory;
use App\Models\SecurityEvent;
use Illuminate\Console\Command;

/**
 * Data retention (Wave 7): prune high-volume, non-financial telemetry beyond the
 * retention window. Deliberately NEVER touches the immutable audit log, the ledger,
 * or compliance records — those are retained per policy. Scheduled weekly.
 */
class ApplyRetentionCommand extends Command
{
    protected $signature = 'poisapay:retention {--days=90}';

    protected $description = 'Prune login history and acknowledged security events older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $logins = LoginHistory::where('created_at', '<', $cutoff)->delete();
        $events = SecurityEvent::whereNotNull('acknowledged_at')->where('created_at', '<', $cutoff)->delete();

        $this->info("Pruned {$logins} login-history and {$events} acknowledged security-event row(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
