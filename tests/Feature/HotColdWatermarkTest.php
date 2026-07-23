<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Reconciliation\HotColdWatermarkMonitor;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Support\Money;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
});

function seedHotBalance(Asset $asset, string $base): void
{
    $ledger = app(LedgerService::class);
    $accounts = app(AccountResolver::class);
    $money = Money::ofBase($base, $asset->decimals, $asset->symbol);

    $ledger->post(new EntryData(
        type: 'test.seed',
        idempotencyKey: "seed:{$asset->id}:{$base}",
        lines: [
            PostingLine::debit($accounts->system(LedgerAccountType::TreasuryHot, $asset->id)->id, $asset->id, $money),
            PostingLine::credit($accounts->system(LedgerAccountType::TreasuryPending, $asset->id)->id, $asset->id, $money),
        ],
    ));
}

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
