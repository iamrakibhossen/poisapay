<?php

declare(strict_types=1);

namespace App\Shop\Models;

use App\Enums\StorageDisk;
use App\Shop\Enums\MediaStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A single image in a merchant's Shop Media Library (Landing Page Builder). The
 * original upload is stored once; responsive `variants` (thumb/medium/large, each
 * with a WebP sibling) are generated on the queue. The storage disk is resolved
 * from config — it is never stored per-row.
 *
 * @property string $id
 * @property string $seller_id
 * @property string $path
 * @property string $name
 * @property string $original_name
 * @property string $mime
 * @property string $extension
 * @property int $size_bytes
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt
 * @property array<string, mixed> $variants
 * @property MediaStatus $status
 * @property string|null $checksum
 * @property Carbon|null $last_used_at
 */
class ShopMedia extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'shop_media';

    protected $fillable = [
        'seller_id', 'path', 'name', 'original_name', 'mime', 'extension',
        'size_bytes', 'width', 'height', 'alt', 'variants', 'status',
        'checksum', 'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'variants' => 'array',
            'status' => MediaStatus::class,
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    /** The single storage disk for all library media (config-resolved). */
    public static function disk(): string
    {
        return StorageDisk::media()->value;
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** Permanent public URL for the original file. */
    public function url(): string
    {
        return Storage::disk(self::disk())->url($this->path);
    }

    /** Public URL for a named variant, falling back to the original. */
    public function variantUrl(string $key): string
    {
        $path = $this->variants[$key]['path'] ?? null;

        return is_string($path) && $path !== '' ? Storage::disk(self::disk())->url($path) : $this->url();
    }

    /** The small preview URL shown in the library grid + field thumbnails. */
    public function previewUrl(): string
    {
        return $this->variantUrl((string) config('media.preview_variant', 'thumb'));
    }

    public function isRaster(): bool
    {
        return in_array($this->mime, (array) config('media.rasterisable', []), true);
    }

    /**
     * Filter by display / original file name.
     *
     * @param  Builder<ShopMedia>  $query
     * @return Builder<ShopMedia>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term === null || trim($term) === '') {
            return $query;
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $query->where(fn (Builder $w) => $w
            ->where('name', 'ilike', $like)
            ->orWhere('original_name', 'ilike', $like)
            ->orWhere('alt', 'ilike', $like));
    }
}
