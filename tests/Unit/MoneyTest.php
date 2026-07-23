<?php

declare(strict_types=1);

use App\Support\Money;

it('builds from base units and formats exactly', function () {
    $m = Money::ofBase('1500000', 6, 'USDT');

    expect($m->toDecimal())->toBe('1.500000')
        ->and($m->format())->toBe('1.50 USDT')   // trailing zeros trimmed, min 2 kept
        ->and($m->baseString())->toBe('1500000');
});

it('trims trailing zeros but keeps significant decimals and a 2dp minimum', function () {
    expect(Money::ofBase('100000000000', 8, 'TRX')->format())->toBe('1,000.00 TRX')   // all zeros -> 2dp
        ->and(Money::ofBase('2048275', 8, 'BNB')->format())->toBe('0.02048275 BNB')    // value present -> full
        ->and(Money::ofBase('150000000', 8, 'USDT')->format())->toBe('1.50 USDT')      // 1.5 -> 1.50
        ->and(Money::ofBase('1980', 2, 'USD')->format())->toBe('19.80 USD');           // fiat unchanged
});

it('builds from a human decimal without float error', function () {
    $m = Money::ofDecimal('0.1', 18, 'ETH');

    expect($m->baseString())->toBe('100000000000000000');
});

it('adds and subtracts exactly at large magnitude', function () {
    $a = Money::ofBase('123456789012345678901234567890', 18, 'ETH');
    $b = Money::ofBase('1', 18, 'ETH');

    expect($a->plus($b)->baseString())->toBe('123456789012345678901234567891')
        ->and($a->minus($a)->isZero())->toBeTrue();
});

it('refuses to combine mismatched scales', function () {
    Money::ofBase('1', 6, 'USDT')->plus(Money::ofBase('1', 18, 'ETH'));
})->throws(InvalidArgumentException::class);

it('compares correctly', function () {
    $small = Money::ofBase('100', 2, 'BDT');
    $big = Money::ofBase('5000', 2, 'BDT');

    expect($small->isLessThan($big))->toBeTrue()
        ->and($big->isGreaterThanOrEqual($small))->toBeTrue()
        ->and($small->isGreaterThanOrEqual($small))->toBeTrue();
});
