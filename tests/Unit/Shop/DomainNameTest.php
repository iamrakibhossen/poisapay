<?php

declare(strict_types=1);

use App\Shop\Support\DomainName;

it('normalizes scheme, case, path, port and www to a bare apex host', function (string $raw, string $expected) {
    expect(DomainName::normalize($raw))->toBe($expected);
})->with([
    'the canonical example' => ['HTTP://WWW.Example.COM/', 'example.com'],
    'https + path + query' => ['https://Shop.Brand.com/pricing?ref=x', 'shop.brand.com'],
    'trailing dot' => ['example.com.', 'example.com'],
    'port stripped' => ['example.com:8443', 'example.com'],
    'userinfo stripped' => ['user@example.com', 'example.com'],
    'whitespace + upper' => ['  EXAMPLE.COM  ', 'example.com'],
    'leading www only' => ['www.shop.example.com', 'shop.example.com'],
    'subdomain preserved' => ['shop.example.com', 'shop.example.com'],
]);

it('converts an IDN host to punycode', function () {
    // bücher.example → xn--bcher-kva.example  (skips if intl is unavailable)
    if (! function_exists('idn_to_ascii')) {
        expect(true)->toBeTrue();

        return;
    }

    expect(DomainName::normalize('BÜCHER.example.com'))->toBe('xn--bcher-kva.example.com');
});

it('accepts valid public FQDNs', function (string $host) {
    expect(DomainName::isValidFormat($host))->toBeTrue();
})->with([
    'example.com',
    'shop.example.com',
    'a.b.c.example.co',
    'my-brand.io',
]);

it('rejects malformed or non-public hosts', function (string $host) {
    expect(DomainName::isValidFormat($host))->toBeFalse();
})->with([
    'no tld' => ['localhost'],
    'empty' => [''],
    'ipv4' => ['127.0.0.1'],
    'leading hyphen label' => ['-bad.example.com'],
    'trailing hyphen label' => ['bad-.example.com'],
    'space' => ['ex ample.com'],
    'underscore' => ['a_b.example.com'],
    'single-char tld' => ['example.c'],
]);
