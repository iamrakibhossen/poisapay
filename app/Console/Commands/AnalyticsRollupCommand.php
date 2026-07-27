<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Analytics\AnalyticsCache;
use App\Domain\Exchange\UsdValuation;
use App\Jobs\RollupAnalyticsJob;
use Illuminate\Console\Command;

/**
 * Rebuild the materialised daily analytics rollups and flush the report cache so
 * the dashboard reflects fresh figures. Scheduled hourly; also runnable on demand.
 */
class AnalyticsRollupCommand extends Command
{
    protected $signature = 'paishapay:analytics-rollup {--days=90 : Days of history to (re)aggregate}';

    protected $description = 'Materialise daily analytics summary tables and flush the analytics cache';

    public function handle(AnalyticsCache $cache): int
    {
        $days = (int) $this->option('days');

        (new RollupAnalyticsJob($days))->handle(app(UsdValuation::class));
        $cache->flush();

        $this->info("Analytics rollup complete ({$days}d) — report cache flushed.");

        return self::SUCCESS;
    }
}
