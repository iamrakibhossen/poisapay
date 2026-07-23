<?php

declare(strict_types=1);

namespace App\Domain\Ops;

use App\Console\Commands\BackupDatabaseCommand;

/**
 * Filesystem helpers for database backups (Wave 7). The dump itself is produced
 * by {@see BackupDatabaseCommand} via pg_dump; this owns the
 * naming + retention so the pruning policy is unit-testable without a live DB.
 */
final class BackupService
{
    public function directory(): string
    {
        return storage_path('app/backups');
    }

    public function newPath(): string
    {
        return $this->directory().'/poisapay-'.now()->format('Ymd-His').'.sql.gz';
    }

    /** Delete gzipped dumps older than $keepDays. Returns the number removed. */
    public function prune(int $keepDays): int
    {
        $dir = $this->directory();
        if (! is_dir($dir)) {
            return 0;
        }

        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;

        foreach (glob($dir.'/poisapay-*.sql.gz') ?: [] as $file) {
            if (filemtime($file) < $cutoff) {
                @unlink($file);
                $removed++;
            }
        }

        return $removed;
    }
}
