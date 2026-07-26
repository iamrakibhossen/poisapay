<?php

declare(strict_types=1);

namespace App\Shop\Services\Files;

use App\Shop\Contracts\FileScanner;
use App\Shop\Enums\FileScanStatus;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Default no-daemon scanner: flags the industry-standard EICAR test signature so
 * the pipeline is verifiable end-to-end, and clears everything else. This is NOT
 * real malware protection — wire {@see ClamAvFileScanner} (or an edge scanner)
 * before trusting seller uploads in production.
 */
final class SimulatedFileScanner implements FileScanner
{
    // The EICAR anti-malware test string — the canonical "safe" way to trip a scanner.
    private const EICAR = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    public function scan(string $disk, string $path): FileScanStatus
    {
        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            // Inconclusive → throw so the job retries rather than passing an absent file.
            throw new RuntimeException("Scan target missing: {$disk}:{$path}");
        }

        $head = substr((string) $storage->get($path), 0, 4096);

        return str_contains($head, self::EICAR) ? FileScanStatus::Infected : FileScanStatus::Clean;
    }
}
