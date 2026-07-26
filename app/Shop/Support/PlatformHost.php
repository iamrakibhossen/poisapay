<?php

declare(strict_types=1);

namespace App\Shop\Support;

/**
 * Recognises hosts owned by the platform itself. The domain router passes these
 * straight through to normal routing, and they can never be connected as a
 * merchant's custom domain (host-header / takeover guard).
 */
final class PlatformHost
{
    public static function is(string $host): bool
    {
        $host = strtolower(trim($host, '. '));

        if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($appHost !== '' && $host === $appHost) {
            return true;
        }

        $cfg = config('shop.custom_domains', []);

        foreach ((array) ($cfg['platform_hosts'] ?? []) as $h) {
            if ($host === strtolower(trim((string) $h))) {
                return true;
            }
        }

        foreach ((array) ($cfg['platform_apexes'] ?? []) as $apex) {
            $apex = strtolower(trim((string) $apex));
            if ($apex !== '' && ($host === $apex || str_ends_with($host, '.'.$apex))) {
                return true;
            }
        }

        return false;
    }
}
