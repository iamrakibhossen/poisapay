<?php

declare(strict_types=1);

use App\Domain\Card\GenerateCardAction;
use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Enums\CardNetwork;
use App\Enums\CardStatus;
use App\Enums\CardType;
use App\Enums\LedgerAccountType;
use App\Models\CardProvider;
use App\Models\User;

it('generates a demo card through a provider with a non-PAN token and BIN-based last4', function () {
    $user = User::factory()->create();
    $provider = CardProvider::create([
        'name' => 'Test Issuer', 'slug' => 'test-issuer', 'network' => 'visa', 'bin' => '453201',
        'supports_virtual' => true, 'supports_physical' => false, 'settlement_currency' => 'USD', 'is_active' => true,
    ]);

    $card = app(GenerateCardAction::class)->execute($user, $provider, CardType::Virtual);

    expect($card->card_provider_id)->toBe($provider->id)
        ->and($card->network)->toBe(CardNetwork::Visa)
        ->and($card->program)->toBe('test-issuer')
        ->and(strlen($card->issuer_card_ref))->toBeGreaterThan(19)   // ck_no_pan safe
        ->and($card->last4)->toHaveLength(4)
        ->and($card->status)->toBe(CardStatus::Inactive);
});

it('refuses a physical card from a virtual-only provider', function () {
    $user = User::factory()->create();
    $provider = CardProvider::create([
        'name' => 'Virtual Only', 'slug' => 'virt-only', 'network' => 'visa',
        'supports_virtual' => true, 'supports_physical' => false, 'is_active' => true,
    ]);

    app(GenerateCardAction::class)->execute($user, $provider, CardType::Physical);
})->throws(RuntimeException::class);

it('refuses an inactive provider', function () {
    $user = User::factory()->create();
    $provider = CardProvider::create([
        'name' => 'Off', 'slug' => 'off', 'network' => 'visa', 'is_active' => false,
    ]);

    app(GenerateCardAction::class)->execute($user, $provider, CardType::Virtual);
})->throws(RuntimeException::class);

it('buys a card by paying the USDT fee from an ETH balance via the Spending Engine', function () {
    updateSetting('spending_engine_enabled', true);
    updateSetting('card_price_virtual', 10); // $10 fee -> 10 USDT

    $usdt = testAsset('USDT', 6, 'tron');
    $eth = testAsset('ETH', 18, 'ethereum');
    seedInventory($usdt, '100000000000');               // house USDT liquidity
    creditUser($user = User::factory()->create(), $eth, '1000000000000000000'); // 1 ETH, no USDT

    $provider = CardProvider::create([
        'name' => 'Fee Issuer', 'slug' => 'fee-issuer', 'network' => 'visa', 'bin' => '453201',
        'supports_virtual' => true, 'supports_physical' => false, 'settlement_currency' => 'USD', 'is_active' => true,
    ]);

    $card = app(GenerateCardAction::class)->execute($user, $provider, CardType::Virtual);

    $ledger = app(LedgerService::class);
    $feeCard = app(AccountResolver::class)->system(LedgerAccountType::FeeCard, $usdt->id)->fresh('balance')->money();

    // The $10 fee accrued to fee:card in USDT, funded by an ETH auto-conversion.
    expect($card->status)->toBe(CardStatus::Inactive)
        ->and($feeCard->baseString())->toBe('10000000') // 10 USDT
        ->and($ledger->availableBalance($user, $eth->id)->isLessThan($eth->money('1000000000000000000')))->toBeTrue();
});
