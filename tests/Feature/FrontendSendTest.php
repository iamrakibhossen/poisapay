<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Models\User;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->sender = User::factory()->create();
    $this->recipient = User::factory()->create(['email' => 'alice@poisapay.test']);
});

it('renders the send page with funded wallets', function () {
    creditUser($this->sender, $this->asset, '3000000');

    actingAs($this->sender)->get(route('send.index'))
        ->assertOk()
        ->assertSee('Send Money')
        ->assertSee('USDT');
});

it('executes a transfer and moves funds', function () {
    creditUser($this->sender, $this->asset, '3000000');

    actingAs($this->sender)->post(route('send.execute'), [
        'recipient' => 'alice@poisapay.test', 'assetId' => $this->asset->id, 'amount' => '1.2', 'memo' => 'lunch',
    ])->assertRedirect(route('send.index'))->assertSessionHas('success');

    expect($this->ledger->availableBalance($this->sender, $this->asset->id)->baseString())->toBe('1800000')
        ->and($this->ledger->availableBalance($this->recipient, $this->asset->id)->baseString())->toBe('1200000');
});

it('resolves the recipient by their numeric PoisaPay ID', function () {
    creditUser($this->sender, $this->asset, '3000000');

    actingAs($this->sender)->post(route('send.execute'), [
        'recipient' => (string) $this->recipient->uid, 'assetId' => $this->asset->id, 'amount' => '1.2',
    ])->assertRedirect(route('send.index'))->assertSessionHas('success');

    expect($this->ledger->availableBalance($this->recipient, $this->asset->id)->baseString())->toBe('1200000');
});

it('notifies both the recipient and the sender', function () {
    creditUser($this->sender, $this->asset, '3000000');
    $this->sender->update(['name' => 'Bob Sender']);

    actingAs($this->sender)->post(route('send.execute'), [
        'recipient' => 'alice@poisapay.test', 'assetId' => $this->asset->id, 'amount' => '1.2',
    ])->assertSessionHas('success');

    // Recipient gets the "money received" alert (the previously-missing one).
    $received = $this->recipient->notifications()->first();
    expect($received)->not->toBeNull()
        ->and($received->data['event'])->toBe('transfer.received')
        ->and($received->data['category'])->toBe('money')
        ->and($received->data['body'])->toContain('Bob Sender');

    // Sender gets a "money sent" record.
    $sent = $this->sender->notifications()->first();
    expect($sent->data['event'])->toBe('transfer.sent');
});

it('reports an unknown recipient', function () {
    creditUser($this->sender, $this->asset, '3000000');

    actingAs($this->sender)->post(route('send.execute'), [
        'recipient' => 'nobody', 'assetId' => $this->asset->id, 'amount' => '1',
    ])->assertSessionHasErrors('recipient');
});

it('rejects sending to yourself', function () {
    creditUser($this->sender, $this->asset, '3000000');

    actingAs($this->sender)->post(route('send.execute'), [
        'recipient' => (string) $this->sender->uid, 'assetId' => $this->asset->id, 'amount' => '1',
    ])->assertSessionHasErrors('recipient');
});

it('rejects a transfer that exceeds the balance', function () {
    creditUser($this->sender, $this->asset, '100000');

    actingAs($this->sender)->post(route('send.execute'), [
        'recipient' => 'alice@poisapay.test', 'assetId' => $this->asset->id, 'amount' => '5',
    ])->assertSessionHasErrors('amount');

    expect($this->ledger->availableBalance($this->sender, $this->asset->id)->baseString())->toBe('100000');
});

it('requires authentication for the send page', function () {
    $this->get(route('send.index'))->assertRedirect(route('login'));
});
