<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\DTO\EntryData;
use App\Domain\Ledger\DTO\PostingLine;
use App\Domain\Ledger\LedgerService;
use App\Enums\LedgerAccountType;
use App\Models\Asset;
use App\Models\CreditLine;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->eth = Asset::firstOrCreate(
        ['symbol' => 'ETH', 'chain_id' => $this->usdt->chain_id, 'contract_address' => 'ETH_T'],
        ['name' => 'Ether', 'kind' => 'crypto', 'decimals' => 18],
    );
    app(AccountResolver::class)->ensureSystemAccounts($this->eth->id);

    $this->ledger = app(LedgerService::class);
    $this->user = User::factory()->create();

    // Seed 1 ETH of collateral to the user (inline; avoids helper redefinition).
    $r = $this->ledger->resolver();
    $t = $r->system(LedgerAccountType::TreasuryPending, $this->eth->id);
    $a = $r->forUser($this->user, LedgerAccountType::UserAvailable, $this->eth->id);
    $this->ledger->post(new EntryData('seed', 'seed:eth:fe:'.$this->user->id, [
        PostingLine::debit($t->id, $this->eth->id, '1000000000000000000'),
        PostingLine::credit($a->id, $this->eth->id, '1000000000000000000'),
    ]));
});

it('renders the credit page via a controller (no Livewire)', function () {
    actingAs($this->user)->get(route('credit'))
        ->assertOk()
        ->assertSee('Credit')
        ->assertSee('Open a credit line')
        ->assertSee('ETH');
});

it('opens a credit line, locking collateral', function () {
    actingAs($this->user)->post(route('credit.open'), [
        'collateralAssetId' => $this->eth->id, 'principalAssetId' => $this->usdt->id, 'collateralAmount' => '1',
    ])->assertRedirect(route('credit'))->assertSessionHas('success');

    expect(CreditLine::where('user_id', $this->user->id)->count())->toBe(1)
        ->and($this->ledger->availableBalance($this->user, $this->eth->id)->baseString())->toBe('0');

    // The page now renders the active line with LTV data.
    actingAs($this->user)->get(route('credit'))
        ->assertOk()->assertSee('Loan-to-value')->assertSee('Draw credit');
});

it('draws principal against the line, crediting the ledger', function () {
    actingAs($this->user)->post(route('credit.open'), [
        'collateralAssetId' => $this->eth->id, 'principalAssetId' => $this->usdt->id, 'collateralAmount' => '1',
    ])->assertRedirect(route('credit'));

    actingAs($this->user)->post(route('credit.draw'), ['drawAmount' => '1000'])
        ->assertRedirect(route('credit'))->assertSessionHas('success');

    expect($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('1000000000');
});

it('rejects a draw with no active line', function () {
    actingAs($this->user)->post(route('credit.draw'), ['drawAmount' => '100'])
        ->assertSessionHasErrors('drawAmount');
});

it('rejects a draw with an invalid amount', function () {
    actingAs($this->user)->post(route('credit.open'), [
        'collateralAssetId' => $this->eth->id, 'principalAssetId' => $this->usdt->id, 'collateralAmount' => '1',
    ])->assertRedirect(route('credit'));

    actingAs($this->user)->post(route('credit.draw'), ['drawAmount' => '0'])
        ->assertSessionHasErrors('drawAmount');
});

it('requires authentication for the credit page', function () {
    $this->get(route('credit'))->assertRedirect(route('login'));
});
