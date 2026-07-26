<?php

declare(strict_types=1);

namespace App\Shop\Jobs;

use App\Shop\Contracts\FileScanner;
use App\Shop\Enums\FileScanStatus;
use App\Shop\Models\ProductFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Malware-scan an uploaded product file off the request path. A `Clean` verdict
 * makes the file deliverable; `Infected` quarantines it — it's dropped as the
 * current version so it can never be granted or downloaded. An inconclusive scan
 * (scanner down) throws → the job retries with backoff, leaving the file `Pending`
 * (undeliverable) in the meantime. Idempotent: re-running just re-scans.
 */
class ScanProductFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly string $productFileId) {}

    public function handle(FileScanner $scanner): void
    {
        $file = ProductFile::find($this->productFileId);
        if (! $file instanceof ProductFile) {
            return;
        }

        $verdict = $scanner->scan($file->disk, $file->path); // throws if inconclusive → retry

        $file->scan_status = $verdict;
        if ($verdict === FileScanStatus::Infected) {
            $file->is_current = false; // quarantine: never the deliverable version
        }
        $file->save();
    }
}
