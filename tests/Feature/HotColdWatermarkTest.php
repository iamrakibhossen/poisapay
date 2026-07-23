<?php

declare(strict_types=1);

use App\Domain\Reconciliation\HotColdWatermarkMonitor;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
});

function watermarkState(): string
{
    return collect(app(HotColdWatermarkMonitor::class)->evaluate())->firstWhere('asset', 'USDT')['state'];
}

it('flags hot above the high-watermark (sweep to cold)', function () {
    seedHotBalance($this->asset, '2000000'); // 2 USDT in hot
    updateSetting('custody.watermark.high.USDT', '1000000', 'custody'); // high = 1 USDT

    expect(watermarkState())->toBe('over');
});

it('flags hot below the low-watermark (refill from cold)', function () {
    seedHotBalance($this->asset, '500000'); // 0.5 USDT
    updateSetting('custody.watermark.low.USDT', '1000000', 'custody'); // low = 1 USDT

    expect(watermarkState())->toBe('under');
});

it('is ok when no watermark is configured (inert by default)', function () {
    seedHotBalance($this->asset, '2000000');

    expect(watermarkState())->toBe('ok');
});
