<?php

declare(strict_types=1);

use App\Domain\Exchange\ExchangeService;
use App\Domain\Exchange\ExecuteSwapAction;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Enums\ConversionContext;
use App\Enums\KycTier;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Conversion;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\Money;
use Laravel\Sanctum\Sanctum;

/** A USD (fiat) asset to swap from — StubRateProvider prices USD and USDT at $1. */
function usdAsset(): Asset
{
    $currency = Currency::firstOrCreate(
        ['symbol' => 'USD'],
        ['name' => 'US Dollar', 'kind' => 'fiat', 'is_stablecoin' => false, 'is_active' => true],
    );

    $asset = Asset::firstOrCreate(
        ['symbol' => 'USD', 'chain_id' => null, 'contract_address' => null],
        ['currency_id' => $currency->id, 'name' => 'US Dollar', 'kind' => 'fiat', 'currency_code' => 'USD', 'decimals' => 2],
    );

    app(AccountResolver::class)->ensureSystemAccounts($asset->id);

    return $asset;
}

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->usd = usdAsset();
    // Treasury holds USDT to deliver to swappers.
    seedHotBalance($this->usdt, '1000000000000'); // 1,000,000 USDT
    $this->ledger = app(LedgerService::class);
    $this->exchange = app(ExchangeService::class);
    $this->action = app(ExecuteSwapAction::class);
});

/** Helper: quote $100.00 USD -> USDT. */
function quoteHundred(): App\Models\FxQuote
{
    return app(ExchangeService::class)->quote(
        test()->user,
        test()->usd,
        test()->usdt,
        Money::ofDecimal('100', 2, 'USD'),
        ConversionContext::Swap,
    );
}

it('with fee_bps=0 books spread only and moves both balances (regression parity)', function () {
    $this->user = User::factory()->create();
    creditUser($this->user, $this->usd, '10000'); // $100.00

    $quote = quoteHundred();
    $this->action->execute($this->user, $quote);

    $resolver = app(AccountResolver::class);
    $spread = $resolver->system(LedgerAccountType::FxSpreadIncome, $this->usd->id)->fresh('balance')->money()->baseString();
    $fee = $resolver->system(LedgerAccountType::FeeIncome, $this->usd->id)->fresh('balance')->money()->baseString();

    // spread = 10000 * 75 / 10000 = 75 minor USD; no platform fee.
    expect($spread)->toBe('75')
        ->and($fee)->toBe('0')
        ->and($this->ledger->availableBalance($this->user, $this->usd->id)->baseString())->toBe('0')
        ->and($this->ledger->availableBalance($this->user, $this->usdt->id)->isPositive())->toBeTrue();
});

it('with a platform fee books BOTH fx:spread_income and fee:income', function () {
    updateSetting('exchange_fee_bps', 50); // 0.50%
    $this->user = User::factory()->create();
    creditUser($this->user, $this->usd, '10000'); // $100.00

    $quote = quoteHundred();
    expect($quote->fee_bps)->toBe(50)->and($quote->market_rate)->not->toBeNull();

    $conversion = $this->action->execute($this->user, $quote);

    $resolver = app(AccountResolver::class);
    $spread = $resolver->system(LedgerAccountType::FxSpreadIncome, $this->usd->id)->fresh('balance')->money()->baseString();
    $fee = $resolver->system(LedgerAccountType::FeeIncome, $this->usd->id)->fresh('balance')->money()->baseString();

    expect($spread)->toBe('75')              // 100.00 * 0.75%
        ->and($fee)->toBe('50')              // 100.00 * 0.50%
        ->and($conversion->spread_amount)->toBe('75')
        ->and($conversion->fee_amount)->toBe('50')
        // Net delivered == user's credited USDT.
        ->and($this->usdt->money($quote->to_amount)->baseString())
        ->toBe($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString());
});

it('is idempotent: a double-confirm of the same quote swaps exactly once', function () {
    $this->user = User::factory()->create();
    creditUser($this->user, $this->usd, '10000'); // $100.00

    $quote = quoteHundred();

    $first = $this->action->execute($this->user, $quote);
    $second = $this->action->execute($this->user, $quote); // replay

    expect($second->id)->toBe($first->id)
        ->and(Conversion::where('quote_id', $quote->id)->count())->toBe(1)
        ->and(JournalEntry::where('type', 'exchange.convert')->count())->toBe(1)
        // Balance moved once: $100 in, $0 left.
        ->and($this->ledger->availableBalance($this->user, $this->usd->id)->baseString())->toBe('0');
});

it('enforces the minimum KYC tier for swaps', function () {
    updateSetting('exchange_min_kyc', 'full');
    $this->user = User::factory()->create(['kyc_tier' => KycTier::Basic]);
    creditUser($this->user, $this->usd, '10000');

    $this->action->execute($this->user, quoteHundred());
})->throws(RuntimeException::class);

it('allows a swap when the user meets the KYC tier', function () {
    updateSetting('exchange_min_kyc', 'full');
    $this->user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    creditUser($this->user, $this->usd, '10000');

    $conversion = $this->action->execute($this->user, quoteHundred());

    expect($conversion->status)->toBe('completed');
});

it('enforces the per-user daily swap notional limit', function () {
    updateSetting('exchange_daily_limit_usd', 150); // allow $150/day
    $this->user = User::factory()->create();
    creditUser($this->user, $this->usd, '30000'); // $300.00

    // First $100 swap ok; a second $100 would breach $150.
    $this->action->execute($this->user, quoteHundred());

    expect(fn () => $this->action->execute($this->user, quoteHundred()))
        ->toThrow(RuntimeException::class);
});

it('writes a swap.completed audit row with before/after balances and rate source', function () {
    $this->user = User::factory()->create();
    creditUser($this->user, $this->usd, '10000');

    $this->action->execute($this->user, quoteHundred());

    $log = AuditLog::where('action', 'swap.completed')->latest()->first();
    expect($log)->not->toBeNull()
        ->and($log->changes['before']['USD'])->toBe('100.00')
        ->and($log->changes['after']['USD'])->toBe('0.00')
        ->and($log->changes['rate_source'])->toBe('reference');
});

it('exposes an idempotent swap API keyed by the Idempotency-Key header', function () {
    $this->user = User::factory()->create();
    creditUser($this->user, $this->usd, '10000');
    Sanctum::actingAs($this->user);

    $quote = $this->postJson('/api/v1/swaps/quote', ['from' => 'USD', 'to' => 'USDT', 'amount' => '100'])
        ->assertCreated()->json('data');

    $headers = ['Idempotency-Key' => 'itest-abc'];
    $a = $this->withHeaders($headers)->postJson('/api/v1/swaps', ['quote_id' => $quote['quote_id']])->assertCreated()->json('data');
    $b = $this->withHeaders($headers)->postJson('/api/v1/swaps', ['quote_id' => $quote['quote_id']])->assertCreated()->json('data');

    expect($a['id'])->toBe($b['id'])
        ->and(Conversion::where('user_id', $this->user->id)->count())->toBe(1);
});
