<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->sender = User::factory()->create();
    $this->recipient = User::factory()->create(['handle' => 'alice']);
});

it('renders the send page with funded wallets', function () {
    creditUser($this->sender, $this->asset, '3000000');

    actingAs($this->sender)->get(route('send'))
        ->assertOk()
        ->assertSee('Send Money')
        ->assertSee('USDT');
});

it('executes a transfer and moves funds', function () {
    creditUser($this->sender, $this->asset, '3000000');

    actingAs($this->sender)->post(route('send.execute'), [
        'recipient' => '@alice', 'assetId' => $this->asset->id, 'amount' => '1.2', 'memo' => 'lunch',
    ])->assertRedirect(route('send'))->assertSessionHas('success');

    expect($this->ledger->availableBalance($this->sender, $this->asset->id)->baseString())->toBe('1800000')
        ->and($this->ledger->availableBalance($this->recipient, $this->asset->id)->baseString())->toBe('1200000');
});

it('reports an unknown recipient', function () {
    creditUser($this->sender, $this->asset, '3000000');

    actingAs($this->sender)->post(route('send.execute'), [
        'recipient' => 'nobody', 'assetId' => $this->asset->id, 'amount' => '1',
    ])->assertSessionHasErrors('recipient');
});

it('rejects sending to yourself', function () {
    creditUser($this->sender, $this->asset, '3000000');
    $this->sender->update(['handle' => 'me']);

    actingAs($this->sender)->post(route('send.execute'), [
        'recipient' => '@me', 'assetId' => $this->asset->id, 'amount' => '1',
    ])->assertSessionHasErrors('recipient');
});

it('rejects a transfer that exceeds the balance', function () {
    creditUser($this->sender, $this->asset, '100000');

    actingAs($this->sender)->post(route('send.execute'), [
        'recipient' => '@alice', 'assetId' => $this->asset->id, 'amount' => '5',
    ])->assertSessionHasErrors('amount');

    expect($this->ledger->availableBalance($this->sender, $this->asset->id)->baseString())->toBe('100000');
});

it('requires authentication for the send page', function () {
    $this->get(route('send'))->assertRedirect(route('login'));
});
