<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerReportService;
use App\Domain\Ledger\LedgerService;
use App\Domain\Ledger\ReverseEntryAction;
use App\Enums\EntryStatus;
use App\Models\JournalEntry;
use App\Models\User;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6);
    $this->ledger = app(LedgerService::class);
    $this->user = User::factory()->create();
});

it('reverses a journal entry and nets the balances back to zero', function () {
    creditUser($this->user, $this->asset, '5000000');
    $before = $this->ledger->availableBalance($this->user, $this->asset->id)->baseString();
    expect($before)->toBe('5000000');

    $entry = JournalEntry::where('type', 'test.credit')->latest()->first();
    $reversal = app(ReverseEntryAction::class)->execute($entry, 'Test correction');

    expect($entry->fresh()->status)->toBe(EntryStatus::Reversed)
        ->and($reversal->reverses_entry_id)->toBe($entry->id)
        ->and($this->ledger->availableBalance($this->user, $this->asset->id)->baseString())->toBe('0');
});

it('refuses to reverse the same entry twice', function () {
    creditUser($this->user, $this->asset, '1000000');
    $entry = JournalEntry::where('type', 'test.credit')->latest()->first();
    app(ReverseEntryAction::class)->execute($entry);
    app(ReverseEntryAction::class)->execute($entry->fresh());
})->throws(RuntimeException::class);

it('produces a balanced trial balance', function () {
    creditUser($this->user, $this->asset, '3000000');

    $tb = app(LedgerReportService::class)->trialBalance();

    expect($tb['balanced'])->toBeTrue()
        ->and($tb['total_debit'])->toBe($tb['total_credit'])
        ->and($tb['rows'])->not->toBeEmpty();
});

it('reports solvency per asset', function () {
    creditUser($this->user, $this->asset, '2000000'); // treasury:pending debited, user credited

    $solvency = app(LedgerReportService::class)->solvency();
    $usdt = collect($solvency)->firstWhere('asset', 'USDT');

    expect($usdt)->not->toBeNull()
        ->and($usdt)->toHaveKeys(['treasury', 'liabilities', 'surplus', 'solvent']);
});
