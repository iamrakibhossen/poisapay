<?php

declare(strict_types=1);

use App\Support\Money;

it('builds from base units and formats exactly', function () {
    $m = Money::ofBase('1500000', 6, 'USDT');

    expect($m->toDecimal())->toBe('1.500000')
        ->and($m->format())->toBe('1.500000 USDT')
        ->and($m->baseString())->toBe('1500000');
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
