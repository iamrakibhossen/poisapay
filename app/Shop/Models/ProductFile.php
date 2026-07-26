<?php

declare(strict_types=1);

namespace App\Shop\Models;

use App\Shop\Enums\FileScanStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property FileScanStatus $scan_status
 */
class ProductFile extends Model
{
    use HasUuids;

    protected $table = 'shop_product_files';

    protected $fillable = [
        'product_id', 'version', 'changelog', 'disk', 'path', 'original_name',
        'size_bytes', 'checksum_sha256', 'scan_status', 'is_current',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'scan_status' => FileScanStatus::class,
        ];
    }

    /** Human file size, e.g. "2.4 MB". */
    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') {
                return ($unit === 'B' ? $bytes : round($bytes, 1)).' '.$unit;
            }
            $bytes /= 1024;
        }

        return $bytes.' B';
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
