<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Settings engine (DollarHub pattern). Key/value settings backed by
 * `system_settings`, cached forever, cleared on write. Every configurable value
 * in the platform flows through here — nothing is hardcoded (admin-editable).
 */
final class Settings
{
    private const PREFIX = 'setting:';

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(self::PREFIX.$key, function () use ($key, $default) {
            $row = SystemSetting::find($key);

            return $row ? $row->value : $default;
        });
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        SystemSetting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        Cache::forget(self::PREFIX.$key);
    }

    /** @return array<string, mixed> */
    public static function group(string $group): array
    {
        return SystemSetting::where('group', $group)->get()->mapWithKeys(fn ($s) => [$s->key => $s->value])->all();
    }

    /** Boolean feature flag (defaults on unless explicitly disabled). */
    public static function enabled(string $key, bool $default = true): bool
    {
        return (bool) self::get($key, $default);
    }

    public static function forget(string $key): void
    {
        Cache::forget(self::PREFIX.$key);
    }

    public static function flush(): void
    {
        SystemSetting::pluck('key')->each(fn ($k) => Cache::forget(self::PREFIX.$k));
    }
}
