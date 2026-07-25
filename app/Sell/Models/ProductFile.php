<?php

declare(strict_types=1);

namespace App\Sell\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFile extends Model
{
    use HasUuids;

    protected $table = 'sell_product_files';

    protected $fillable = [
        'product_id', 'version', 'changelog', 'disk', 'path', 'original_name',
        'size_bytes', 'checksum_sha256', 'scan_status', 'is_current',
    ];

    protected function casts(): array
    {
        return ['is_current' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
