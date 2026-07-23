<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\Chain;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/**
 * Provision a chain + crypto asset for tests and warm its system accounts.
 */
function testAsset(string $symbol = 'USDT', int $decimals = 6, string $chainKey = 'tron'): Asset
{
    $chain = Chain::firstOrCreate(
        ['key' => $chainKey],
        ['name' => ucfirst($chainKey), 'native_symbol' => 'TRX', 'min_confirmations' => 19, 'is_evm' => false],
    );

    $currency = Currency::firstOrCreate(
        ['symbol' => $symbol],
        ['name' => $symbol, 'kind' => 'crypto', 'is_stablecoin' => $symbol === 'USDT', 'is_active' => true],
    );

    $asset = Asset::firstOrCreate(
        ['symbol' => $symbol, 'chain_id' => $chain->id, 'contract_address' => $symbol === $chain->native_symbol ? null : 'T'.$symbol],
        ['currency_id' => $currency->id, 'name' => $symbol, 'kind' => 'crypto', 'decimals' => $decimals, 'is_stablecoin' => $symbol === 'USDT'],
    );

    app(AccountResolver::class)->ensureSystemAccounts($asset->id);

    return $asset;
}

/** Credit a user's available balance directly (test convenience: treasury -> user). */
function creditUser(User $user, Asset $asset, string $baseAmount): void
{
    $ledger = app(LedgerService::class);
    $resolver = $ledger->resolver();

    $treasury = $resolver->system(LedgerAccountType::TreasuryPending, $asset->id);
    $available = $resolver->forUser($user, LedgerAccountType::UserAvailable, $asset->id);

    $ledger->post(new EntryData(
        type: 'test.credit',
        idempotencyKey: 'test:credit:'.$user->id.':'.uniqid('', true),
        lines: [
            PostingLine::debit($treasury->id, $asset->id, $baseAmount),
            PostingLine::credit($available->id, $asset->id, $baseAmount),
        ],
    ));
}

/** Seed a treasury:hot balance for an asset (test convenience: pending -> hot). */
function seedHotBalance(Asset $asset, string $baseAmount): void
{
    $ledger = app(LedgerService::class);
    $resolver = $ledger->resolver();

    $hot = $resolver->system(LedgerAccountType::TreasuryHot, $asset->id);
    $pending = $resolver->system(LedgerAccountType::TreasuryPending, $asset->id);

    $ledger->post(new EntryData(
        type: 'test.seed',
        idempotencyKey: 'test:seedhot:'.$asset->id.':'.uniqid('', true),
        lines: [
            PostingLine::debit($hot->id, $asset->id, $baseAmount),
            PostingLine::credit($pending->id, $asset->id, $baseAmount),
        ],
    ));
}
