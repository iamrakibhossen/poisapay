<?php

declare(strict_types=1);

namespace App\Shop\Services\Media;

use App\Shop\Models\ShopMedia;
use Illuminate\Support\Facades\Storage;

/**
 * Deletion lifecycle for library media. A plain delete is a soft delete (files
 * stay on disk so the asset is restorable); purge permanently removes the row and
 * every file. Restore brings a soft-deleted asset back.
 */
final class MediaDeleteService
{
    public function __construct(
        private readonly MediaVariantService $variants,
        private readonly MediaUrlService $urls,
    ) {}

    /** Soft delete — recoverable via {@see restore()}; files are retained. */
    public function delete(ShopMedia $media): void
    {
        $this->urls->forget($media);
        $media->delete();
    }

    /** Restore a soft-deleted asset. */
    public function restore(ShopMedia $media): void
    {
        $media->restore();
        $this->urls->forget($media);
    }

    /** Permanently remove the row + original + all variant files. */
    public function purge(ShopMedia $media): void
    {
        $this->variants->deleteVariantFiles($media);
        Storage::disk(ShopMedia::disk())->delete($media->path);
        $this->urls->forget($media);
        $media->forceDelete();
    }
}
