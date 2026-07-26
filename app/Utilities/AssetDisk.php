<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Enums\StorageDisk;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A disk-bound media uploader — obtained via {@see Asset::disk()}. Holds the actual
 * store/url/delete logic; {@see Asset}'s static entry points delegate here on the
 * configured media disk, so `Asset::store(...)` and `Asset::disk('local')->store(...)`
 * share one code path.
 */
final class AssetDisk
{
    public function __construct(private readonly StorageDisk $disk) {}

    /**
     * Store the request's file for `$name` under a mime-bucketed, date-bucketed
     * unique path; delete + replace `$old` (and its thumbnail) on a new upload, else
     * return `$old` unchanged. `$thumbnail: false` skips the 120px image thumbnail.
     */
    public function store(Request $request, string $name, ?string $old = null, bool $thumbnail = false): ?string
    {
        if (! $request->hasFile($name)) {
            return $old;
        }

        $file = $request->file($name);
        $mimeType = (string) $file->getMimeType();
        $directory = Str::plural(Str::before($mimeType, '/')); // images, videos, applications…

        $this->removeFile($old);
        $this->removeFile(Asset::buildThumbnailPath($old));

        $path = Asset::generateUploadPath($file->getClientOriginalName(), $directory);
        Storage::disk($this->disk->value)->put($path, $file->getContent());

        if ($thumbnail && Str::startsWith($mimeType, 'image/')) {
            $this->generateThumbnail($file, $path);
        }

        return $path;
    }

    /** Public URL for a stored asset (absolute/root-relative paths pass through). */
    public function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }
        if (str_starts_with($path, 'http') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk($this->disk->value)->url($path);
    }

    public function removeFile(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        $disk = Storage::disk($this->disk->value);
        if (! $disk->exists($path)) {
            return false;
        }

        $deleted = @$disk->delete($path);

        Cache::forget('asset:exists:'.md5($path));
        Cache::forget('asset:thumb:'.md5($path));

        return $deleted;
    }

    /**
     * Best-effort 120px PNG thumbnail alongside the original, via GD (no external
     * package). Never fatal — a failure just means the full image is used.
     */
    private function generateThumbnail(UploadedFile $file, string $originalPath): bool
    {
        if (! function_exists('imagecreatefromstring')) {
            return false;
        }

        $src = @imagecreatefromstring($file->getContent());
        if ($src === false) {
            return false;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(120 / $w, 120 / $h, 1);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $thumb = imagecreatetruecolor($nw, $nh);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagepng($thumb);
        $data = (string) ob_get_clean();

        imagedestroy($src);
        imagedestroy($thumb);

        Storage::disk($this->disk->value)->put(Asset::buildThumbnailPath($originalPath), $data);

        return true;
    }
}
