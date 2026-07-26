<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The filesystem disks the app writes to. Media uploads (avatars, store logos,
 * product covers) resolve their disk from {@see self::media()} — a fixed policy,
 * never a caller argument.
 */
enum StorageDisk: string
{
    case Public = 'public';
    case Local = 'local';
    case S3 = 's3';

    /**
     * The disk all user-visible media uploads live on. Driven by config, so moving
     * media to S3 is a `MEDIA_DISK=s3` env change (the `s3` disk is already defined
     * in config/filesystems.php) — no code change, and every upload/URL/delete
     * follows automatically.
     */
    public static function media(): self
    {
        return self::tryFrom((string) config('filesystems.media', self::Public->value)) ?? self::Public;
    }
}
