<?php

declare(strict_types=1);

use App\Domain\Analytics\Period;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('produces a stable hourly cache signature for now-anchored windows', function () {
    CarbonImmutable::setTestNow('2026-07-24 10:05:07');
    $early = Period::resolve('last_30_days')->signature();

    CarbonImmutable::setTestNow('2026-07-24 10:55:41'); // same hour, ~50 min later
    $late = Period::resolve('last_30_days')->signature();

    CarbonImmutable::setTestNow('2026-07-24 11:00:02'); // next hour
    $nextHour = Period::resolve('last_30_days')->signature();

    // Same hour ⇒ same key (cache hits). New hour ⇒ new key (recompute).
    expect($early)->toBe($late)
        ->and($early)->not->toBe($nextHour);
});

it('keeps distinct presets on distinct keys', function () {
    CarbonImmutable::setTestNow('2026-07-24 10:05:00');

    expect(Period::resolve('today')->signature())
        ->not->toBe(Period::resolve('this_month')->signature());
});
