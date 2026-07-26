<?php

declare(strict_types=1);

namespace App\Shop\Support;

/**
 * Domain-name normalization + format validation.
 *
 * Normalization canonicalises arbitrary user input to a bare, lowercase, ASCII
 * (punycode) FQDN — e.g. `HTTP://WWW.Example.COM/` → `example.com`. A leading
 * `www.` is stripped: www is treated as an alias of the apex and served by the
 * router, so we store (and verify) one canonical host per site.
 */
final class DomainName
{
    /** Canonicalise raw input to a bare lowercase FQDN (empty string if unparseable). */
    public static function normalize(string $raw): string
    {
        $host = trim($raw);

        // Drop scheme, userinfo, path/query/fragment, and port — keep only the host.
        $host = preg_replace('#^[a-z][a-z0-9+.\-]*://#i', '', $host) ?? $host;
        $host = preg_split('#[/?\#]#', $host, 2)[0] ?? $host;
        if (str_contains($host, '@')) {
            $host = substr($host, (int) strrpos($host, '@') + 1);
        }
        $host = preg_replace('#:\d+$#', '', $host) ?? $host;
        $host = strtolower(trim($host, ". \t\n\r\0\x0B"));

        // IDN → punycode so comparisons and DNS lookups are ASCII-stable.
        if ($host !== '' && ! self::isAscii($host) && function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($ascii) && $ascii !== '') {
                $host = strtolower($ascii);
            }
        }

        // Canonical apex: www is an alias, not a distinct site.
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    /** Is a normalized host a structurally valid, public FQDN (not an IP)? */
    public static function isValidFormat(string $host): bool
    {
        if ($host === '' || strlen($host) > 253) {
            return false;
        }

        // An IP address is never a connectable custom domain.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        // labels: 1–63 chars of [a-z0-9-], no leading/trailing hyphen; a real TLD
        // (alpha, or an `xn--` punycode TLD). Requires at least one dot.
        $label = '(?!-)[a-z0-9-]{1,63}(?<!-)';
        $tld = '(?:[a-z]{2,63}|xn--[a-z0-9-]{2,59})';

        return preg_match('/^(?:'.$label.'\.)+'.$tld.'$/', $host) === 1;
    }

    private static function isAscii(string $value): bool
    {
        return preg_match('/[^\x00-\x7F]/', $value) !== 1;
    }
}
