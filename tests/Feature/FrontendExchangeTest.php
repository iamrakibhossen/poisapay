<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\FxQuote;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->trx = Asset::firstOrCreate(
        ['symbol' => 'TRX', 'chain_id' => $this->usdt->chain_id, 'contract_address' => null],
        ['name' => 'Tron', 'kind' => 'crypto', 'decimals' => 18],
    );
    app(AccountResolver::class)->ensureSystemAccounts($this->trx->id);

    $this->ledger = app(LedgerService::class);
    $resolver = $this->ledger->resolver();
    $liab = $resolver->system(LedgerAccountType::LiabilityUserFunds, $this->trx->id);
    $hot = $resolver->system(LedgerAccountType::TreasuryHot, $this->trx->id);
    $this->ledger->post(new EntryData(
        type: 'seed.treasury',
        idempotencyKey: 'seed:treasury:trx',
        lines: [
            PostingLine::debit($hot->id, $this->trx->id, '100000000000000000000000'),
            PostingLine::credit($liab->id, $this->trx->id, '100000000000000000000000'),
        ],
    ));

    $this->user = User::factory()->create();
});

it('renders the exchange page with the swap form', function () {
    creditUser($this->user, $this->usdt, '10000000');

    actingAs($this->user)->get(route('exchange'))
        ->assertOk()
        ->assertSee('Exchange')
        ->assertSee('USDT');
});

it('quotes a swap and flashes it to the session', function () {
    creditUser($this->user, $this->usdt, '10000000');

    $res = actingAs($this->user)->post(route('exchange.quote'), [
        'fromAssetId' => $this->usdt->id, 'toAssetId' => $this->trx->id, 'fromAmount' => '10',
    ])->assertRedirect(route('exchange'))->assertSessionHas('quote');

    $quote = $res->getSession()->get('quote');
    expect($quote['fromSymbol'])->toBe('USDT')
        ->and($quote)->toHaveKeys(['quoteId', 'toAmount', 'rate', 'expiresAt']);
});

it('shows the quote details on the page after quoting', function () {
    creditUser($this->user, $this->usdt, '10000000');

    actingAs($this->user)->post(route('exchange.quote'), [
        'fromAssetId' => $this->usdt->id, 'toAssetId' => $this->trx->id, 'fromAmount' => '10',
    ]);

    actingAs($this->user)->get(route('exchange'))
        ->assertOk()
        ->assertSee('Confirm swap')
        ->assertSee('Quote expires in');
});

it('rejects quoting two identical assets', function () {
    creditUser($this->user, $this->usdt, '10000000');

    actingAs($this->user)->post(route('exchange.quote'), [
        'fromAssetId' => $this->usdt->id, 'toAssetId' => $this->usdt->id, 'fromAmount' => '1',
    ])->assertSessionHasErrors('toAssetId');
});

it('confirms a quote and moves both balances', function () {
    creditUser($this->user, $this->usdt, '10000000');

    $quoteId = actingAs($this->user)->post(route('exchange.quote'), [
        'fromAssetId' => $this->usdt->id, 'toAssetId' => $this->trx->id, 'fromAmount' => '10',
    ])->getSession()->get('quote')['quoteId'];

    actingAs($this->user)->post(route('exchange.confirm'), ['quoteId' => $quoteId])
        ->assertRedirect(route('exchange'))->assertSessionHas('success');

    expect($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('0')
        ->and($this->ledger->availableBalance($this->user, $this->trx->id)->isPositive())->toBeTrue();
});

it('rejects confirming an expired quote', function () {
    creditUser($this->user, $this->usdt, '10000000');

    $quoteId = actingAs($this->user)->post(route('exchange.quote'), [
        'fromAssetId' => $this->usdt->id, 'toAssetId' => $this->trx->id, 'fromAmount' => '10',
    ])->getSession()->get('quote')['quoteId'];

    FxQuote::whereKey($quoteId)->update(['expires_at' => now()->subMinute()]);

    actingAs($this->user)->post(route('exchange.confirm'), ['quoteId' => $quoteId])
        ->assertSessionHasErrors('quoteId');
});

it('cannot confirm another user\'s quote', function () {
    creditUser($this->user, $this->usdt, '10000000');
    $quoteId = actingAs($this->user)->post(route('exchange.quote'), [
        'fromAssetId' => $this->usdt->id, 'toAssetId' => $this->trx->id, 'fromAmount' => '10',
    ])->getSession()->get('quote')['quoteId'];

    $intruder = User::factory()->create();
    actingAs($intruder)->post(route('exchange.confirm'), ['quoteId' => $quoteId])
        ->assertSessionHasErrors('quoteId');
});

it('requires authentication for the exchange page', function () {
    $this->get(route('exchange'))->assertRedirect(route('login'));
});
