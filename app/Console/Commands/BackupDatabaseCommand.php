<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Ops\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

/**
 * Database backup (Wave 7): gzip a pg_dump to storage/app/backups, then prune old
 * dumps. Scheduled daily; ship the dumps off-box (S3/rsync) in production. The
 * retention window is --keep days (default 14).
 */
class BackupDatabaseCommand extends Command
{
    protected $signature = 'poisapay:backup {--keep=14}';

    protected $description = 'Back up the PostgreSQL database (pg_dump + gzip) and prune old backups';

    public function handle(BackupService $backups): int
    {
        $connection = config('database.default');
        if ($connection !== 'pgsql') {
            $this->warn("Backup supports pgsql only; current connection is [{$connection}]. Skipping.");

            return self::SUCCESS;
        }

        $db = config("database.connections.{$connection}");
        @mkdir($backups->directory(), 0755, true);
        $path = $backups->newPath();

        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s --no-owner | gzip > %s',
            escapeshellarg((string) ($db['password'] ?? '')),
            escapeshellarg((string) ($db['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($db['port'] ?? '5432')),
            escapeshellarg((string) ($db['username'] ?? '')),
            escapeshellarg((string) ($db['database'] ?? '')),
            escapeshellarg($path),
        );

        $result = Process::timeout(1800)->run('bash -lc '.escapeshellarg($command));

        if (! $result->successful()) {
            $this->error('Backup failed: '.trim($result->errorOutput()));

            return self::FAILURE;
        }

        $pruned = $backups->prune((int) $this->option('keep'));
        $this->info('Backup written to '.$path.' (pruned '.$pruned.' old backup(s)).');

        return self::SUCCESS;
    }
}
