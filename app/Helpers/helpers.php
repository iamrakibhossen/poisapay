<?php

declare(strict_types=1);

use App\Domain\Notification\AdminNotifier;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;

if (! function_exists('getSetting')) {
    /** Read a configurable setting (cached). */
    function getSetting(string $key, mixed $default = null): mixed
    {
        return Settings::get($key, $default);
    }
}

if (! function_exists('updateSetting')) {
    function updateSetting(string $key, mixed $value, string $group = 'general'): void
    {
        Settings::set($key, $value, $group);
    }
}

if (! function_exists('feature')) {
    /** Feature-flag gate (settings-based, DollarHub pattern): feature('cards_enabled'). */
    function feature(string $key, bool $default = true): bool
    {
        return Settings::enabled($key, $default);
    }
}

if (! function_exists('settingsGroup')) {
    /** @return array<string, mixed> */
    function settingsGroup(string $group): array
    {
        return Settings::group($group);
    }
}

if (! function_exists('permissionGroup')) {
    /** Derive a permission's admin-UI group from its name (DollarHub pattern). */
    function permissionGroup(string $name): string
    {
        foreach (config('permissions.groups', []) as $group => $needles) {
            foreach ((array) $needles as $needle) {
                if (str_contains($name, $needle)) {
                    return $group;
                }
            }
        }

        return 'Other';
    }
}

if (! function_exists('notifyAdmins')) {
    /** Fan a notification to operator accounts (DollarHub pattern). */
    function notifyAdmins(string $title, string $body, ?string $url = null, string $category = 'general'): void
    {
        app(AdminNotifier::class)->notify($title, $body, $url, $category);
    }
}

if (! function_exists('mediaUrl')) {
    /** Resolve a stored media path to a URL, with a fallback. */
    function mediaUrl(?string $path, ?string $default = null): string
    {
        if (blank($path)) {
            return $default ?? '';
        }
        if (str_starts_with($path, 'http') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}

if (! function_exists('siteName')) {
    function siteName(): string
    {
        return (string) getSetting('site_name', config('app.name', 'PoisaPay'));
    }
}
