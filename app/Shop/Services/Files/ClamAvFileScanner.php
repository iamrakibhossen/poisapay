<?php

declare(strict_types=1);

namespace App\Shop\Services\Files;

use App\Shop\Contracts\FileScanner;
use App\Shop\Enums\FileScanStatus;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Real ClamAV driver — runs `clamdscan` against the file and maps its exit code to
 * a verdict (0 = clean, 1 = infected, anything else = inconclusive → throw so the
 * job retries). Remote-disk files are streamed to a temp path first. Requires a
 * running clamd; set `SHOP_FILES_SCANNER=clamav` only once that's provisioned.
 */
final class ClamAvFileScanner implements FileScanner
{
    public function scan(string $disk, string $path): FileScanStatus
    {
        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            throw new RuntimeException("Scan target missing: {$disk}:{$path}");
        }

        // Prefer a local absolute path; fall back to a temp copy for remote disks.
        $local = null;
        $temp = null;
        try {
            $local = $storage->path($path);
            if (! is_file($local)) {
                $temp = tempnam(sys_get_temp_dir(), 'ppscan_');
                file_put_contents($temp, $storage->get($path));
                $local = $temp;
            }

            $binary = (string) config('shop.files.clamav_binary', 'clamdscan');
            $process = new Process([$binary, '--no-summary', '--fdpass', $local]);
            $process->setTimeout((float) config('shop.files.scan_timeout', 120));
            $process->run();

            return match ($process->getExitCode()) {
                0 => FileScanStatus::Clean,
                1 => FileScanStatus::Infected,
                default => throw new RuntimeException('ClamAV inconclusive: '.$process->getErrorOutput()),
            };
        } finally {
            if ($temp !== null && is_file($temp)) {
                @unlink($temp);
            }
        }
    }
}
