<?php

declare(strict_types=1);

namespace App\Shop\Contracts;

use App\Shop\Enums\FileScanStatus;

/**
 * Scans an uploaded product file for malware. Behind a contract so the default
 * `simulated` driver (signature heuristics, no daemon) can be swapped for a real
 * `clamav` integration via config — wire ClamAV before relying on this in prod.
 */
interface FileScanner
{
    /**
     * Scan the stored file and return a terminal verdict — {@see FileScanStatus::Clean}
     * or {@see FileScanStatus::Infected}. Implementations should throw on an
     * *inconclusive* result (scanner down, timeout) so the job retries rather than
     * green-lighting an unscanned file.
     */
    public function scan(string $disk, string $path): FileScanStatus;
}
