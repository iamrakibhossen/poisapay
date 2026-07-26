<?php

declare(strict_types=1);

namespace App\Shop\Services\Media;

use App\Shop\Enums\MediaStatus;
use App\Shop\Jobs\ProcessMediaImage;
use App\Shop\Models\Seller;
use App\Shop\Models\ShopMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ingests uploads into the Media Library: dedup by content hash, permanent path,
 * synchronous original store (so its URL works immediately), and queued variant
 * generation. Also handles replace (bytes swapped in place, URL preserved) and
 * metadata edits (name / alt).
 */
final class MediaUploadService
{
    public function __construct(
        private readonly MediaVariantService $variants,
        private readonly MediaUrlService $urls,
    ) {}

    /**
     * Store an upload (deduped per seller). Returns the existing asset when the same
     * bytes were already uploaded, so files are never duplicated.
     */
    public function upload(Seller $seller, UploadedFile $file): ShopMedia
    {
        $binary = (string) $file->get();
        $checksum = hash('sha256', $binary);

        $existing = $seller->media()->where('checksum', $checksum)->first();
        if ($existing instanceof ShopMedia) {
            return $existing;
        }

        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        $ext = $this->extension($file, $mime);

        if ($mime === 'image/svg+xml') {
            $binary = $this->variants->sanitizeSvg($binary);
        }

        $path = $this->buildPath($seller, $ext);
        Storage::disk(ShopMedia::disk())->put($path, $binary, 'public');

        $raster = in_array($mime, (array) config('media.rasterisable', []), true);
        [$width, $height] = $raster ? $this->variants->dimensions($binary) : $this->variants->svgDimensions($binary);

        $media = $seller->media()->create([
            'path' => $path,
            'name' => $this->displayName($file->getClientOriginalName()),
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime' => $mime,
            'extension' => $ext,
            'size_bytes' => strlen($binary),
            'width' => $width,
            'height' => $height,
            'variants' => [],
            'status' => $raster ? MediaStatus::Processing : MediaStatus::Ready,
            'checksum' => $checksum,
        ]);

        if ($raster) {
            ProcessMediaImage::dispatch($media->id);
        }

        return $media;
    }

    /**
     * Replace an asset's bytes IN PLACE — the URL/path is preserved, so every page
     * and section already referencing it updates automatically.
     */
    public function replace(ShopMedia $media, UploadedFile $file): ShopMedia
    {
        $binary = (string) $file->get();
        if (($file->getMimeType() ?: '') === 'image/svg+xml') {
            $binary = $this->variants->sanitizeSvg($binary);
        }

        $this->variants->deleteVariantFiles($media);
        Storage::disk(ShopMedia::disk())->put($media->path, $binary, 'public');

        $raster = $media->isRaster();
        [$width, $height] = $raster ? $this->variants->dimensions($binary) : $this->variants->svgDimensions($binary);

        $media->update([
            'size_bytes' => strlen($binary),
            'checksum' => hash('sha256', $binary),
            'width' => $width,
            'height' => $height,
            'variants' => [],
            'status' => $raster ? MediaStatus::Processing : MediaStatus::Ready,
        ]);
        $this->urls->forget($media);

        if ($raster) {
            ProcessMediaImage::dispatch($media->id);
        }

        return $media;
    }

    /** Edit display name and/or alt text. */
    public function updateMeta(ShopMedia $media, ?string $name, ?string $alt): ShopMedia
    {
        $update = [];
        if ($name !== null && trim($name) !== '') {
            $update['name'] = $this->displayName($name);
        }
        if ($alt !== null) {
            $update['alt'] = mb_substr($alt, 0, 300);
        }
        if ($update !== []) {
            $media->update($update);
        }

        return $media;
    }

    private function buildPath(Seller $seller, string $ext): string
    {
        $dir = trim((string) config('media.directory', 'media'), '/');

        return sprintf('%s/%s/%s/%s.%s', $dir, $seller->id, date('Y/m'), Str::random(24), $ext);
    }

    private function extension(UploadedFile $file, string $mime): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext !== '') {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }

        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            default => 'bin',
        };
    }

    private function displayName(string $name): string
    {
        return mb_substr(trim($name) !== '' ? trim($name) : 'Untitled', 0, 200);
    }
}
