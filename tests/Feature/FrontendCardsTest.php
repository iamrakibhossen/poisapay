<?php

declare(strict_types=1);

use App\Enums\KycTier;
use App\Models\Card;
use App\Models\CardProvider;
use App\Models\User;

use function Pest\Laravel\actingAs;

/** A fully-verified user who can issue cards. */
function cardsUser(): User
{
    return User::factory()->create(['kyc_tier' => KycTier::Full]);
}

function seedProvider(): CardProvider
{
    return CardProvider::create([
        'name' => 'Test Issuer', 'slug' => 'test-issuer', 'network' => 'visa', 'bin' => '453201',
        'supports_virtual' => true, 'supports_physical' => true, 'settlement_currency' => 'USD', 'is_active' => true, 'sort' => 0,
    ]);
}

function makeCard(User $user, array $overrides = []): Card
{
    return Card::create(array_merge([
        'user_id' => $user->id, 'program' => 'poisapay-demo', 'type' => 'virtual', 'network' => 'visa',
        'issuer_card_ref' => 'tok_'.str_repeat('d', 24), 'last4' => '7777', 'status' => 'active', 'settlement_currency' => 'USD',
    ], $overrides));
}

it('renders the cards list page', function () {
    actingAs(cardsUser())->get(route('cards'))->assertOk();
});

it('renders the cards list with the card portfolio', function () {
    $user = cardsUser();
    seedProvider();
    makeCard($user, ['last4' => '4242']);

    actingAs($user)->get(route('cards'))
        ->assertOk()
        ->assertSee('4242')
        ->assertDontSee('Test Issuer'); // provider is hidden from users now
});

it('renders the card manage page for its owner', function () {
    $user = cardsUser();
    $card = makeCard($user);

    actingAs($user)->get(route('cards.manage', $card))->assertOk()->assertSee('7777');
});

it('never exposes a PAN, CVV, PIN hash or issuer ref on the manage page', function () {
    $user = cardsUser();
    $card = makeCard($user);

    $body = actingAs($user)->get(route('cards.manage', $card))->assertOk()->getContent();

    expect($body)->not->toContain('pin_hash')
        ->and($body)->not->toContain($card->issuer_card_ref);
});

it('forbids the manage page for a non-owner (smoke parity)', function () {
    $card = makeCard(cardsUser());

    actingAs(User::factory()->create())->get(route('cards.manage', $card))->assertForbidden();
});

it('generates a card and redirects back with a flash message', function () {
    $user = cardsUser();
    seedProvider(); // provider is auto-resolved server-side; user does not choose

    actingAs($user)->post(route('cards.generate'), [
        'cardType' => 'virtual',
    ])->assertRedirect(route('cards'))->assertSessionHas('success');

    expect(Card::where('user_id', $user->id)->count())->toBe(1);
});

it('blocks generation for a non-fully-verified user', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Basic]);
    seedProvider();

    actingAs($user)->post(route('cards.generate'), [
        'cardType' => 'virtual',
    ])->assertSessionHasErrors('cardType');

    expect(Card::where('user_id', $user->id)->count())->toBe(0);
});

it('activates an inactive card', function () {
    $user = cardsUser();
    $card = makeCard($user, ['status' => 'inactive']);

    actingAs($user)->post(route('cards.activate', $card))
        ->assertRedirect(route('cards'))->assertSessionHas('success');

    expect($card->fresh()->status->value)->toBe('active');
});

it('freezes and unfreezes an active card', function () {
    $user = cardsUser();
    $card = makeCard($user, ['status' => 'active']);

    actingAs($user)->post(route('cards.freeze', $card))
        ->assertRedirect(route('cards'))->assertSessionHas('success');
    expect($card->fresh()->status->value)->toBe('frozen');

    actingAs($user)->post(route('cards.freeze', $card))->assertRedirect(route('cards'));
    expect($card->fresh()->status->value)->toBe('active');
});

it('saves spend controls via the manage endpoint', function () {
    $user = cardsUser();
    $card = makeCard($user);

    actingAs($user)->put(route('card.controls', $card), [
        'nickname' => 'Travel card',
        'online_enabled' => '1',
        'contactless_enabled' => '1',
        'daily_limit' => '250.00',
        'per_tx_limit' => '',
        'allowed_countries' => 'us, gb',
        'blocked_mccs' => '7995',
    ])->assertRedirect(route('cards.manage', $card->id))->assertSessionHas('success');

    $fresh = $card->fresh();
    expect($fresh->nickname)->toBe('Travel card')
        ->and((int) $fresh->daily_limit)->toBe(25000)
        ->and($fresh->allowed_countries)->toBe(['US', 'GB']);
});

it('sets a card PIN and only stores a hash', function () {
    $user = cardsUser();
    $card = makeCard($user);

    actingAs($user)->post(route('card.pin', $card), ['pin' => '1234'])
        ->assertRedirect(route('cards.manage', $card->id))->assertSessionHas('success');

    expect($card->fresh()->pin_hash)->not->toBeNull()
        ->and($card->fresh()->pin_hash)->not->toBe('1234');
});

it('closes a card', function () {
    $user = cardsUser();
    $card = makeCard($user);

    actingAs($user)->post(route('card.close', $card))
        ->assertRedirect(route('cards'))->assertSessionHas('success');

    expect($card->fresh()->status->value)->toBe('closed');
});

it('blocks all mutations on another user\'s card', function () {
    $owner = cardsUser();
    $card = makeCard($owner);
    $intruder = cardsUser();

    actingAs($intruder)->post(route('cards.activate', $card))->assertForbidden();
    actingAs($intruder)->post(route('cards.freeze', $card))->assertForbidden();
    actingAs($intruder)->get(route('cards.manage', $card))->assertForbidden();
    actingAs($intruder)->put(route('card.controls', $card), ['nickname' => 'x'])->assertForbidden();
    actingAs($intruder)->post(route('card.pin', $card), ['pin' => '4321'])->assertForbidden();
    actingAs($intruder)->post(route('card.freeze', $card))->assertForbidden();
    actingAs($intruder)->post(route('card.close', $card))->assertForbidden();

    // The card is untouched.
    expect($card->fresh()->status->value)->toBe('active');
});
