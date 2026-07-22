<?php

declare(strict_types=1);

use App\Domain\Card\GenerateCardAction;
use App\Domain\Transaction\TransactionFeedService;
use App\Enums\CardAuthStatus;
use App\Enums\CardType;
use App\Events\CardTransactionSettled;
use App\Listeners\HandleCardTransactionSettled;
use App\Models\CardAuthorization;
use App\Models\CardProvider;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

function feedCard(User $user, string $status = 'settled', string $merchant = 'Test Store'): CardAuthorization
{
    $provider = CardProvider::firstOrCreate(['slug' => 'mock-issuer'], [
        'name' => 'Mock Issuer', 'driver' => 'mock', 'network' => 'visa',
        'supports_virtual' => true, 'supports_physical' => false, 'settlement_currency' => 'USD', 'is_active' => true,
    ]);
    $card = app(GenerateCardAction::class)->execute($user, $provider, CardType::Virtual);

    return CardAuthorization::create([
        'card_id' => $card->id, 'network_auth_id' => 'auth_'.$status.'_'.$merchant,
        'amount' => 2500, 'currency_code' => 'USD', 'merchant' => $merchant,
        'status' => CardAuthStatus::from($status),
    ]);
}

it('shows a settled card transaction in the activity feed', function () {
    $user = User::factory()->create();
    feedCard($user, 'settled', 'Coffee House');

    $items = collect(app(TransactionFeedService::class)->feed($user)['items'])->where('group', 'cards')->values();

    expect($items)->toHaveCount(1)
        ->and($items[0]['title'])->toBe('Coffee House')
        ->and($items[0]['amount'])->toBe('-USD 25.00')
        ->and($items[0]['status'])->toBe('Settled');
});

it('shows a reversal as money back in the feed', function () {
    $user = User::factory()->create();
    feedCard($user, 'reversed', 'Refund Store');

    $items = collect(app(TransactionFeedService::class)->feed($user)['items'])->where('group', 'cards')->values();

    expect($items[0]['amount'])->toBe('+USD 25.00');
});

it('notifies the cardholder when a card transaction settles', function () {
    Notification::fake();
    $user = User::factory()->create();
    $auth = feedCard($user, 'settled', 'Diner');

    (new HandleCardTransactionSettled)->handle(new CardTransactionSettled($auth->id));

    Notification::assertSentTo($user, App\Notifications\LedgerEventNotification::class,
        fn ($n) => $n->event === 'card.settled' && str_contains($n->body, 'Diner'));
});
