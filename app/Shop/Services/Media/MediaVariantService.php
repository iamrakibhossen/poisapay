<?php

declare(strict_types=1);

namespace App\Shop\Services\Media;

use App\Shop\Enums\MediaStatus;
use App\Shop\Models\ShopMedia;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Image processing for the Media Library: intrinsic dimensions, SVG sanitisation,
 * and responsive-variant generation (thumb/medium/large + a WebP sibling each),
 * metadata-stripped and only ever downscaled. GD-backed via intervention/image.
 */
final class MediaVariantService
{
    private readonly ImageManager $manager;

    public function __construct()
    {
        $this->manager = ImageManager::gd();
    }

    /**
     * Intrinsic pixel size of a raster binary, or [null, null] if unreadable.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public function dimensions(string $binary): array
    {
        try {
            $image = $this->manager->read($binary);

            return [$image->width(), $image->height()];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    /**
     * Best-effort intrinsic size for an SVG (width/height attrs, else viewBox).
     *
     * @return array{0: int|null, 1: int|null}
     */
    public function svgDimensions(string $svg): array
    {
        if (preg_match('/\bwidth="([\d.]+)"/i', $svg, $w) && preg_match('/\bheight="([\d.]+)"/i', $svg, $h)) {
            return [(int) round((float) $w[1]), (int) round((float) $h[1])];
        }
        if (preg_match('/viewBox="[\d.\s-]*?([\d.]+)\s+([\d.]+)"/i', $svg, $m)) {
            return [(int) round((float) $m[1]), (int) round((float) $m[2])];
        }

        return [null, null];
    }

    /**
     * Strip active content from an SVG before it is ever stored/served: scripts,
     * event handlers, external entities, and javascript: URLs. SVG renders as
     * active content same-origin, so an unsanitised upload is stored XSS.
     */
    public function sanitizeSvg(string $svg): string
    {
        $svg = preg_replace('/<\?xml.*?\?>/is', '', $svg) ?? $svg;
        $svg = preg_replace('#<!DOCTYPE.*?>#is', '', $svg) ?? $svg;
        $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg) ?? $svg;
        $svg = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $svg) ?? $svg;
        $svg = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $svg) ?? $svg;
        $svg = preg_replace("/\son\w+\s*=\s*'[^']*'/i", '', $svg) ?? $svg;
        $svg = preg_replace('/(href|xlink:href|src)\s*=\s*(["\'])\s*(javascript|data:text)[^"\']*\2/i', '$1="#"', $svg) ?? $svg;

        return trim($svg);
    }

    /**
     * Generate + store the responsive variants for a media asset and mark it ready.
     * Idempotent: variant paths are deterministic, so a retry simply overwrites.
     * SVG/GIF (non-raster) have no variants — they are marked ready immediately.
     */
    public function generate(ShopMedia $media): void
    {
        if (! $media->isRaster()) {
            $media->update(['status' => MediaStatus::Ready]);

            return;
        }

        try {
            $disk = ShopMedia::disk();
            $binary = (string) Storage::disk($disk)->get($media->path);

            $source = $this->manager->read($binary);
            $srcMime = $source->origin()->mediaType();
            $srcMax = max($source->width(), $source->height());

            $sizes = (array) config('media.variants', []);
            asort($sizes);

            $map = [];
            $emitted = [];
            foreach ($sizes as $key => $cap) {
                $cap = (int) $cap;
                $target = min($cap, $srcMax);
                if (in_array($target, $emitted, true)) {
                    continue;
                }
                $emitted[] = $target;

                // Fresh read per variant so successive downscales don't compound.
                $img = $this->manager->read($binary)->scaleDown(width: $cap, height: $cap);

                if (($native = $this->encode($img, $srcMime)) !== null) {
                    $map[$key] = $this->store($disk, $media->path, $key, $native['ext'], $native['mime'], $native['binary'], $img);
                }
                $webp = (string) $img->toWebp((int) config('media.quality.webp', 80));
                $map[$key.'_webp'] = $this->store($disk, $media->path, $key.'_webp', 'webp', 'image/webp', $webp, $img);
            }

            $media->update([
                'width' => $source->width(),
                'height' => $source->height(),
                'variants' => $map,
                'status' => MediaStatus::Ready,
            ]);
        } catch (\Throwable $e) {
            $media->update(['status' => MediaStatus::Failed]);
            report($e);
        }
    }

    /** Remove every generated variant file for a media asset (original untouched). */
    public function deleteVariantFiles(ShopMedia $media): void
    {
        $paths = array_values(array_filter(array_map(
            fn ($v) => is_array($v) ? ($v['path'] ?? null) : null,
            $media->variants,
        )));
        if ($paths !== []) {
            Storage::disk(ShopMedia::disk())->delete($paths);
        }
    }

    /** @return array{path: string, width: int, height: int, mime: string} */
    private function store(string $disk, string $basePath, string $suffix, string $ext, string $mime, string $binary, ImageInterface $img): array
    {
        $path = $this->variantPath($basePath, $suffix, $ext);
        Storage::disk($disk)->put($path, $binary, 'public');

        return ['path' => $path, 'width' => $img->width(), 'height' => $img->height(), 'mime' => $mime];
    }

    /**
     * Re-encode a variant in the original's format (jpeg/png/webp), stripping metadata.
     *
     * @return array{binary: string, ext: string, mime: string}|null
     */
    private function encode(ImageInterface $img, string $mime): ?array
    {
        return match ($mime) {
            'image/png' => ['binary' => (string) $img->toPng(), 'ext' => 'png', 'mime' => 'image/png'],
            'image/webp' => ['binary' => (string) $img->toWebp((int) config('media.quality.webp', 80)), 'ext' => 'webp', 'mime' => 'image/webp'],
            'image/jpeg' => ['binary' => (string) $img->toJpeg((int) config('media.quality.jpeg', 82)), 'ext' => 'jpg', 'mime' => 'image/jpeg'],
            default => null,
        };
    }

    private function variantPath(string $basePath, string $suffix, string $ext): string
    {
        $dir = trim((string) pathinfo($basePath, PATHINFO_DIRNAME), '.');
        $name = pathinfo($basePath, PATHINFO_FILENAME);
        $prefix = $dir !== '' ? $dir.'/' : '';

        return "{$prefix}{$name}__{$suffix}.{$ext}";
    }
}
