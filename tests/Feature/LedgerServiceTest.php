<?php

declare(strict_types=1);

use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\JournalEntry;
use App\Models\LedgerLine;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    $this->ledger = app(LedgerService::class);
    $this->asset = testAsset('USDT', 6);
    $this->user = User::factory()->create();
});

it('credits a user and reflects the exact available balance', function () {
    creditUser($this->user, $this->asset, '2500000'); // 2.5 USDT

    expect($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())
        ->toBe('2500000');
});

it('rejects an unbalanced entry before it touches the database', function () {
    $available = $this->ledger->resolver()->forUser($this->user, LedgerAccountType::UserAvailable, $this->asset->id);
    $treasury = $this->ledger->resolver()->system(LedgerAccountType::TreasuryPending, $this->asset->id);

    new EntryData(
        type: 'bad.entry',
        idempotencyKey: 'bad:1',
        lines: [
            PostingLine::debit($treasury->id, $this->asset->id, '100'),
            PostingLine::credit($available->id, $this->asset->id, '99'), // imbalance
        ],
    );

    $this->ledger->post(new EntryData(
        type: 'bad.entry',
        idempotencyKey: 'bad:2',
        lines: [
            PostingLine::debit($treasury->id, $this->asset->id, '100'),
            PostingLine::credit($available->id, $this->asset->id, '99'),
        ],
    ));
})->throws(InvalidArgumentException::class);

it('is idempotent — a replayed key never double-credits', function () {
    $available = $this->ledger->resolver()->forUser($this->user, LedgerAccountType::UserAvailable, $this->asset->id);
    $treasury = $this->ledger->resolver()->system(LedgerAccountType::TreasuryPending, $this->asset->id);

    $data = new EntryData(
        type: 'deposit.credit',
        idempotencyKey: 'deposit:0xabc:0',
        lines: [
            PostingLine::debit($treasury->id, $this->asset->id, '1000000'),
            PostingLine::credit($available->id, $this->asset->id, '1000000'),
        ],
    );

    $first = $this->ledger->post($data);
    $second = $this->ledger->post($data); // replay

    expect($second->id)->toBe($first->id)
        ->and(JournalEntry::where('type', 'deposit.credit')->count())->toBe(1)
        ->and($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('1000000');
});

it('locks funds available -> locked and blocks over-spend', function () {
    creditUser($this->user, $this->asset, '1000000');

    $this->ledger->lock($this->user, $this->asset->id, Money::ofBase('600000', 6, 'USDT'), 'wd:lock:1');

    expect($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('400000')
        ->and($this->ledger->lockedBalance($this->user, $this->asset->id)->baseString())->toBe('600000');
});

it('throws when locking more than the available balance', function () {
    creditUser($this->user, $this->asset, '100000');

    $this->ledger->lock($this->user, $this->asset->id, Money::ofBase('999999', 6, 'USDT'), 'wd:lock:overspend');
})->throws(RuntimeException::class);

it('unlocks funds locked -> available', function () {
    creditUser($this->user, $this->asset, '1000000');
    $this->ledger->lock($this->user, $this->asset->id, Money::ofBase('600000', 6, 'USDT'), 'wd:lock:2');
    $this->ledger->unlock($this->user, $this->asset->id, Money::ofBase('600000', 6, 'USDT'), 'wd:unlock:2');

    expect($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('1000000')
        ->and($this->ledger->lockedBalance($this->user, $this->asset->id)->baseString())->toBe('0');
});

it('keeps the system solvent: sum of user credits equals treasury debits', function () {
    creditUser($this->user, $this->asset, '3000000');

    $liability = LedgerLine::query()
        ->whereHas('account', fn ($q) => $q->where('type', LedgerAccountType::UserAvailable->value))
        ->where('side', 'credit')->sum('amount');
    $treasuryOut = LedgerLine::query()
        ->whereHas('account', fn ($q) => $q->where('type', LedgerAccountType::TreasuryPending->value))
        ->where('side', 'debit')->sum('amount');

    expect((string) $liability)->toBe('3000000')
        ->and((string) $treasuryOut)->toBe('3000000');
});
