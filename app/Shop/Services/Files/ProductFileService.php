<?php

declare(strict_types=1);

namespace App\Shop\Services\Files;

use App\Shop\Enums\FileScanStatus;
use App\Shop\Jobs\ScanProductFile;
use App\Shop\Models\Product;
use App\Shop\Models\ProductFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Owns the seller's downloadable product files: stores each upload on a PRIVATE
 * disk (never public), records a checksummed, versioned {@see ProductFile} as the
 * new current version, supersedes the previous one, and queues a malware scan. A
 * file is `Pending` (undeliverable) until {@see ScanProductFile} clears it.
 */
class ProductFileService
{
    private function disk(): string
    {
        return (string) config('shop.files.disk', 'local');
    }

    /**
     * Store an uploaded file as the product's new current version and queue its scan.
     */
    public function store(Product $product, UploadedFile $file, ?string $changelog = null): ProductFile
    {
        $disk = $this->disk();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $path = "shop/files/{$product->seller_id}/{$product->getKey()}/".Str::uuid()->toString().'.'.$ext;

        Storage::disk($disk)->put($path, $file->get(), 'private');

        return DB::transaction(function () use ($product, $file, $disk, $path, $changelog) {
            $next = $product->files()->count() + 1;

            // The new upload becomes the sole current version.
            $product->files()->update(['is_current' => false]);

            $record = ProductFile::create([
                'product_id' => $product->getKey(),
                'version' => 'v'.$next,
                'changelog' => $changelog,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size_bytes' => $file->getSize(),
                'checksum_sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
                'scan_status' => FileScanStatus::Pending,
                'is_current' => true,
            ]);

            ScanProductFile::dispatch($record->getKey());

            return $record;
        });
    }

    /** Remove a file's stored bytes + its record. */
    public function delete(ProductFile $file): void
    {
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();
    }
}
