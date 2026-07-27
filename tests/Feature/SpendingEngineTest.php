<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Domain\Spending\DTO\SpendRequest;
use App\Domain\Spending\Enums\SpendPurpose;
use App\Domain\Spending\Events\FundsSpent;
use App\Domain\Spending\Exceptions\InsufficientBalanceException;
use App\Domain\Spending\Exceptions\InsufficientLiquidityException;
use App\Domain\Spending\SpendingEngine;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\Conversion;
use App\Models\User;
use App\Models\UserSpendingPriority;
use App\Support\Money;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');   // settlement coin (stablecoin, $1)
    $this->eth = testAsset('ETH', 18, 'ethereum'); // convertible, $3,200
    $this->engine = app(SpendingEngine::class);
    $this->ledger = app(LedgerService::class);
    $this->accounts = app(AccountResolver::class);
    $this->payer = User::factory()->create();
    $this->payee = User::factory()->create();
});

/** Build a single-destination spend (all of $amount credited to the payee). */
function spendTo(User $payer, User $payee, Asset $settlement, Money $amount, SpendPurpose $purpose, string $idem): SpendRequest
{
    $acct = app(AccountResolver::class)->forUser($payee, LedgerAccountType::UserAvailable, $settlement->id);

    return new SpendRequest(
        user: $payer,
        settlementAsset: $settlement,
        amount: $amount,
        purpose: $purpose,
        destination: [PostingLine::credit($acct->id, $settlement->id, $amount)],
        idempotencyKey: $idem,
    );
}

function usdtBalance(User $user): Money
{
    return app(LedgerService::class)->availableBalance($user, test()->usdt->id);
}

it('spends the settlement asset 1:1 with no conversion', function () {
    creditUser($this->payer, $this->usdt, '100000000'); // 100 USDT

    $amount = Money::ofDecimal('100', 6, 'USDT');
    $result = $this->engine->spend(spendTo($this->payer, $this->payee, $this->usdt, $amount, SpendPurpose::InternalCheckout, 'spend:1'));

    expect(usdtBalance($this->payer)->baseString())->toBe('0')
        ->and(usdtBalance($this->payee)->baseString())->toBe('100000000')
        ->and($result->legs)->toHaveCount(1)
        ->and($result->legs[0]->converted)->toBeFalse()
        ->and(Conversion::count())->toBe(0);
});

it('consumes partial balances then auto-converts the remainder', function () {
    seedInventory($this->usdt, '100000000000'); // 100,000 USDT house liquidity
    creditUser($this->payer, $this->usdt, '20000000');  // 20 USDT
    creditUser($this->payer, $this->eth, '1000000000000000000'); // 1 ETH (~$3.2k)

    $amount = Money::ofDecimal('100', 6, 'USDT');
    $result = $this->engine->spend(spendTo($this->payer, $this->payee, $this->usdt, $amount, SpendPurpose::ShopCheckout, 'spend:2'));

    // Payee received the full 100 USDT; the 20 direct + a ETH conversion covered it.
    expect(usdtBalance($this->payee)->baseString())->toBe('100000000')
        ->and($result->legs)->toHaveCount(2)
        ->and($result->legs[0]->converted)->toBeFalse()   // 20 USDT direct
        ->and($result->legs[1]->converted)->toBeTrue()     // ETH conversion
        ->and(Conversion::where('context', 'spend')->count())->toBe(1)
        // ETH was spent; the payer keeps at most conversion dust in USDT.
        ->and($this->ledger->availableBalance($this->payer, $this->eth->id)->isLessThan($this->eth->money('1000000000000000000')))->toBeTrue();
});

it('auto-converts entirely when the user holds no settlement balance', function () {
    seedInventory($this->usdt, '100000000000');
    creditUser($this->payer, $this->eth, '1000000000000000000'); // 1 ETH only

    $amount = Money::ofDecimal('100', 6, 'USDT');
    $result = $this->engine->spend(spendTo($this->payer, $this->payee, $this->usdt, $amount, SpendPurpose::MerchantPayment, 'spend:3'));

    expect(usdtBalance($this->payee)->baseString())->toBe('100000000')
        ->and(collect($result->legs)->every(fn ($l) => $l->converted))->toBeTrue()
        ->and($result->conversionIds())->toHaveCount(1);
});

it('follows a user custom priority over the settlement-first default', function () {
    seedInventory($this->usdt, '100000000000');
    creditUser($this->payer, $this->usdt, '100000000'); // 100 USDT
    creditUser($this->payer, $this->eth, '1000000000000000000'); // 1 ETH

    // Custom order: ETH before USDT — so ETH funds the spend even though USDT is held.
    UserSpendingPriority::create(['user_id' => $this->payer->id, 'position' => 1, 'asset_id' => $this->eth->id]);
    UserSpendingPriority::create(['user_id' => $this->payer->id, 'position' => 2, 'asset_id' => $this->usdt->id]);

    $amount = Money::ofDecimal('50', 6, 'USDT');
    $result = $this->engine->spend(spendTo($this->payer, $this->payee, $this->usdt, $amount, SpendPurpose::BillPayment, 'spend:4'));

    expect($result->legs[0]->asset->symbol)->toBe('ETH')
        ->and($result->legs[0]->converted)->toBeTrue()
        // USDT was untouched (still >= the original 100), ETH was drawn down.
        ->and(usdtBalance($this->payer)->isGreaterThanOrEqual($this->usdt->money('100000000')))->toBeTrue()
        ->and($this->ledger->availableBalance($this->payer, $this->eth->id)->isLessThan($this->eth->money('1000000000000000000')))->toBeTrue();
});

it('rejects when no combination of balances can cover the amount', function () {
    creditUser($this->payer, $this->usdt, '10000000'); // only 10 USDT

    $amount = Money::ofDecimal('100', 6, 'USDT');

    expect(fn () => $this->engine->spend(spendTo($this->payer, $this->payee, $this->usdt, $amount, SpendPurpose::PaymentLink, 'spend:5')))
        ->toThrow(InsufficientBalanceException::class, 'Insufficient balance.');

    expect(usdtBalance($this->payer)->baseString())->toBe('10000000'); // nothing moved
});

it('rejects a conversion the platform lacks liquidity to settle', function () {
    // ETH balance but ZERO house USDT inventory — the conversion cannot settle.
    creditUser($this->payer, $this->eth, '1000000000000000000');

    $amount = Money::ofDecimal('100', 6, 'USDT');

    expect(fn () => $this->engine->spend(spendTo($this->payer, $this->payee, $this->usdt, $amount, SpendPurpose::CardPurchase, 'spend:6')))
        ->toThrow(InsufficientLiquidityException::class, 'Insufficient platform liquidity for automatic conversion.');

    expect($this->ledger->availableBalance($this->payer, $this->eth->id)->baseString())->toBe('1000000000000000000'); // untouched
});

it('is idempotent on the settlement idempotency key', function () {
    creditUser($this->payer, $this->usdt, '100000000');
    $amount = Money::ofDecimal('40', 6, 'USDT');

    $a = $this->engine->spend(spendTo($this->payer, $this->payee, $this->usdt, $amount, SpendPurpose::Subscription, 'spend:7'));
    $b = $this->engine->spend(spendTo($this->payer, $this->payee, $this->usdt, $amount, SpendPurpose::Subscription, 'spend:7'));

    expect($b->entry->id)->toBe($a->entry->id)
        ->and($b->replayed)->toBeTrue()
        ->and(usdtBalance($this->payee)->baseString())->toBe('40000000'); // credited once
});

it('books exchange spread and emits FundsSpent', function () {
    Event::fake([FundsSpent::class]);
    seedInventory($this->usdt, '100000000000');
    creditUser($this->payer, $this->eth, '1000000000000000000');

    $amount = Money::ofDecimal('100', 6, 'USDT');
    $this->engine->spend(spendTo($this->payer, $this->payee, $this->usdt, $amount, SpendPurpose::QrPayment, 'spend:8'));

    $spread = $this->accounts->system(LedgerAccountType::FxSpreadIncome, $this->eth->id)->fresh('balance')->money();

    expect($spread->isPositive())->toBeTrue();
    Event::assertDispatched(FundsSpent::class);
});
