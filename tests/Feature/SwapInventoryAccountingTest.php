<?php

declare(strict_types=1);

use App\Domain\Analytics\Period;
use App\Domain\Analytics\Reports\TreasuryReport;
use App\Domain\Exchange\ExchangeService;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerReportService;
use App\Domain\Ledger\LedgerService;
use App\Enums\ConversionContext;
use App\Enums\LedgerAccountType as T;
use App\Models\Asset;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Balance of a system (platform) account, as a base-unit string. */
function sysBal(T $type, int $assetId): string
{
    $acct = app(AccountResolver::class)->system($type, $assetId);

    return (string) (DB::table('account_balances')->where('account_id', $acct->id)->value('balance') ?? '0');
}

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->trx = Asset::firstOrCreate(
        ['symbol' => 'TRX', 'chain_id' => $this->usdt->chain_id, 'contract_address' => null],
        ['name' => 'Tron', 'kind' => 'crypto', 'decimals' => 18],
    );
    app(AccountResolver::class)->ensureSystemAccounts($this->trx->id);

    $this->exchange = app(ExchangeService::class);
    $this->user = User::factory()->create();
});

it('never moves custody (treasury:hot) on an internal swap', function () {
    seedInventory($this->trx, '100000000000000000000000'); // house TRX inventory
    creditUser($this->user, $this->usdt, '10000000');       // user has 10 USDT

    $hotUsdtBefore = sysBal(T::TreasuryHot, $this->usdt->id);
    $hotTrxBefore = sysBal(T::TreasuryHot, $this->trx->id);
    $invTrxBefore = sysBal(T::TradingInventory, $this->trx->id);

    $quote = $this->exchange->quote($this->user, $this->usdt, $this->trx, Money::ofBase('10000000', 6, 'USDT'), ConversionContext::Swap);
    $this->exchange->execute($this->user, $quote, 'swap:test:1');

    // Custody is untouched — it only ever moves on real chain/bank events.
    expect(sysBal(T::TreasuryHot, $this->usdt->id))->toBe($hotUsdtBefore)
        ->and(sysBal(T::TreasuryHot, $this->trx->id))->toBe($hotTrxBefore);

    // The dealer inventory carried the position: USDT inventory up, TRX inventory down.
    expect(bccomp(sysBal(T::TradingInventory, $this->usdt->id), '0'))->toBe(1)
        ->and(bccomp($invTrxBefore, sysBal(T::TradingInventory, $this->trx->id)))->toBe(1);

    // User balances moved.
    $ledger = app(LedgerService::class);
    expect($ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('0')
        ->and($ledger->availableBalance($this->user, $this->trx->id)->isPositive())->toBeTrue();
});

it('rejects a swap when dealer inventory is insufficient', function () {
    // User holds TRX, but the house has NO USDT inventory to deliver.
    creditUser($this->user, $this->trx, '5000000000000000000'); // 5 TRX

    $quote = $this->exchange->quote($this->user, $this->trx, $this->usdt, Money::ofBase('5000000000000000000', 18, 'TRX'), ConversionContext::Swap);

    expect(fn () => $this->exchange->execute($this->user, $quote, 'swap:test:noliq'))
        ->toThrow(RuntimeException::class, 'liquidity');
});

it('keeps proof-of-reserves intact after a swap (custody >= liabilities per asset)', function () {
    seedInventory($this->trx, '100000000000000000000000');
    creditUser($this->user, $this->usdt, '10000000');

    // Back the user's USDT with real custody so the from-asset is solvent too.
    seedHotBalance($this->usdt, '10000000');

    $quote = $this->exchange->quote($this->user, $this->usdt, $this->trx, Money::ofBase('10000000', 6, 'USDT'), ConversionContext::Swap);
    $this->exchange->execute($this->user, $quote, 'swap:test:solvency');

    foreach (app(LedgerReportService::class)->solvency() as $row) {
        expect($row['solvent'])->toBeTrue();
    }
});

it('rejects a cross-asset-unbalanced journal entry at the DB trigger', function () {
    $resolver = app(AccountResolver::class);
    $pendUsdt = $resolver->system(T::TreasuryPending, $this->usdt->id);
    $pendTrx = $resolver->system(T::TreasuryPending, $this->trx->id);

    $entryId = (string) Str::uuid();
    DB::table('journal_entries')->insert([
        'id' => $entryId, 'type' => 'test.bad', 'idempotency_key' => 'bad:'.$entryId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    // Raw base-unit totals cancel (1000 − 1000 = 0) but the entry is NOT balanced
    // per asset (USDT +1000, TRX −1000). The balance trigger is DEFERRED, so force
    // it to fire now with SET CONSTRAINTS ALL IMMEDIATE — it must reject this.
    $post = function () use ($entryId, $pendUsdt, $pendTrx) {
        DB::table('ledger_lines')->insert([
            ['id' => (string) Str::uuid(), 'entry_id' => $entryId, 'account_id' => $pendUsdt->id, 'asset_id' => $this->usdt->id, 'side' => 'debit', 'amount' => '1000', 'created_at' => now()],
            ['id' => (string) Str::uuid(), 'entry_id' => $entryId, 'account_id' => $pendTrx->id, 'asset_id' => $this->trx->id, 'side' => 'credit', 'amount' => '1000', 'created_at' => now()],
        ]);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    };

    expect($post)->toThrow(QueryException::class, 'imbalance');
});

it('injects house dealer inventory via poisapay:inject-inventory and enables swaps', function () {
    creditUser($this->user, $this->usdt, '10000000'); // 10 USDT

    // No TRX inventory yet. Inject house liquidity: DR treasury:hot / CR dealer:inventory.
    $this->artisan('poisapay:inject-inventory', ['asset' => $this->trx->id, 'amount' => '1000000'])
        ->assertSuccessful();

    expect(bccomp(sysBal(T::TreasuryHot, $this->trx->id), '0'))->toBe(1)      // custody up (equity contribution)
        ->and(bccomp(sysBal(T::TradingInventory, $this->trx->id), '0'))->toBe(1); // dealer inventory up

    // The swap that would have been rejected now succeeds.
    $quote = $this->exchange->quote($this->user, $this->usdt, $this->trx, Money::ofBase('10000000', 6, 'USDT'), ConversionContext::Swap);
    $this->exchange->execute($this->user, $quote, 'swap:after-inject');

    expect(app(LedgerService::class)->availableBalance($this->user, $this->trx->id)->isPositive())->toBeTrue();
});

it('exposes the treasury liquidity + dealer-position metrics', function () {
    seedInventory($this->trx, '100000000000000000000000');
    creditUser($this->user, $this->usdt, '10000000');

    $report = app(TreasuryReport::class)->build(Period::resolve('this_month'));
    $labels = collect($report['kpis'])->pluck('label');

    expect($labels)->toContain(
        'On-chain Treasury Assets', 'Customer Liabilities', 'Available Liquidity',
        'Reserve Ratio', 'Dealer Net Position',
    );
    // The dealer-inventory-by-asset table is present.
    expect(collect($report['tables'])->pluck('title'))->toContain('TradingInventory by asset');
});
