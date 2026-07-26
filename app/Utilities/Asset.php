<?php

declare(strict_types=1);

namespace App\Utilities;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class Asset
{
    public static function fileName(string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        return Str::random(10).($extension ? '.'.strtolower($extension) : '');
    }

    /**
     * The single entry point for public media uploads. Stores the request's file
     * for `$name` under a date-bucketed unique path and returns the stored path;
     * deletes and replaces `$old` when a new file is present, and returns `$old`
     * unchanged when the request carries no file (so edits keep the current image).
     */
    public static function store(Request $request, string $name, ?string $old = null, string $directory = 'images', ?string $disk = 'public'): ?string
    {
        if (! $request->hasFile($name)) {
            return $old;
        }

        self::removeFile($old, $disk);
        $file = $request->file($name);
        $path = self::generateUploadPath($file->getClientOriginalName(), $directory);
        Storage::disk($disk ?? config('filesystems.default'))->put($path, $file->getContent());

        return $path;
    }

    /**
     * Public URL for a stored asset. Absolute URLs and root-relative paths pass
     * through unchanged; everything else resolves against the given disk.
     */
    public static function url(?string $path, ?string $disk = 'public'): ?string
    {
        if (blank($path)) {
            return null;
        }
        if (str_starts_with($path, 'http') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk($disk ?? 'public')->url($path);
    }

    public static function generateUploadPath(string $fileName, string $directory = 'images'): string
    {
        $fileName = self::fileName($fileName);
        $fileName = Str::lower($fileName);
        $unique = bin2hex(random_bytes(8));

        $datePath = implode('/', array_map(
            fn ($part) => hash('crc32b', $part),
            [date('Y'), date('m'), date('d')]
        ));

        return "uploads/{$directory}/{$datePath}/{$unique}{$fileName}";
    }

    public static function removeFile(?string $path, ?string $disk = null): bool
    {
        if (! $path) {
            return false;
        }

        $disk = Storage::disk($disk ?? config('filesystems.default'));

        if (! $disk->exists($path)) {
            return false;
        }

        $deleted = @$disk->delete($path);

        Cache::forget('asset:exists:'.md5($path));
        Cache::forget('asset:thumb:'.md5($path));

        return $deleted;
    }

    public static function fileExtension(?string $path): ?string
    {
        $extension = Str::afterLast($path, '.');

        return $extension === $path ? null : $extension;
    }

    public static function fileMimeType(?string $extension): ?string
    {
        return match ($extension) {
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'ogg' => 'video/ogg',
            'm3u8' => 'application/x-mpegURL',
            'ts' => 'video/MP2T',
            '3gp' => 'video/3gpp',
            'flv' => 'video/x-flv',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'avi' => 'video/x-msvideo',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'm4a' => 'audio/x-m4a',
            'aac' => 'audio/aac',
            'wma' => 'audio/x-ms-wma',
            'flac' => 'audio/flac',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'rtf' => 'application/rtf',
            'html' => 'text/html',
            'htm' => 'text/html',
            'php' => 'text/x-php',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => '',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',
            'srt' => 'text/plain',
            'vtt' => 'text/vtt',
            default => null,
        };
    }

    public static function buildThumbnailPath(?string $originalPath, string $extension = 'png'): string
    {
        if (empty($originalPath)) {
            return '';
        }

        $pathInfo = pathinfo($originalPath);

        return sprintf(
            '%s/%s_thumb.%s',
            $pathInfo['dirname'] ?? '',
            $pathInfo['filename'] ?? 'thumbnail',
            $extension
        );
    }

    public static function getThumbnailPath(?string $originalPath): string
    {
        if (empty($originalPath)) {
            return '';
        }

        return Cache::rememberForever('asset:thumb:'.md5($originalPath), function () use ($originalPath) {
            $pathInfo = pathinfo($originalPath);
            $disk = Storage::disk(config('filesystems.default'));

            $pngThumbnail = static::buildThumbnailPath($originalPath);

            if ($disk->exists($pngThumbnail)) {
                return $pngThumbnail;
            }

            $originalThumbnail = static::buildThumbnailPath($originalPath, $pathInfo['extension'] ?? 'png');

            return $disk->exists($originalThumbnail) ? $originalThumbnail : $originalPath;
        });
    }
}
