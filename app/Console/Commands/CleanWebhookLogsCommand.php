<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Prune inbound webhook logs older than the retention window. High-volume telemetry;
 * deleted in bounded batches so it never locks the table. Scheduled daily.
 */
class CleanWebhookLogsCommand extends Command
{
    protected $signature = 'poisapay:webhooks-clean {--days=7}';

    protected $description = 'Delete inbound webhook_logs records older than the retention window';

    private const BATCH_SIZE = 1000;

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $deleted = 0;

        // chunkById() can't be used — the random-UUID PK makes its cursor skip rows.
        do {
            $batch = DB::table('webhook_logs')
                ->whereIn('id', fn (Builder $q) => $q
                    ->select('id')
                    ->from('webhook_logs')
                    ->where('created_at', '<=', $cutoff)
                    ->limit(self::BATCH_SIZE))
                ->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("Deleted {$deleted} webhook-log record(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
