<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Domain\Withdrawal\RequestWithdrawalAction;
use App\Enums\KycTier;
use App\Models\User;
use App\Support\Money;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->chain = $this->asset->chain;
    $this->ledger = app(LedgerService::class);
});

it('toggles a favorite asset via a form POST', function () {
    $user = User::factory()->create();
    creditUser($user, $this->asset, '1000000');

    actingAs($user)->post(route('wallet.favorite', $this->asset->id))->assertRedirect();

    expect($user->favoriteAssets()->count())->toBe(1);
});

it('requests a withdrawal through the withdraw page (reserves funds)', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Full]);
    // older account so it is not risk-flagged into review
    $user->forceFill(['created_at' => now()->subMonth()])->save();
    creditUser($user, $this->asset, '5000000');

    actingAs($user)->get(route('withdraw'))->assertOk();

    // Backend path (the UI wraps this) reserves funds available -> locked.
    $w = app(RequestWithdrawalAction::class)->execute(
        $user, $this->asset, Money::ofBase('2000000', 6, 'USDT'), 'TdestAddr', 'ui-wd-1'
    );

    expect($this->ledger->lockedBalance($user, $this->asset->id)->isPositive())->toBeTrue()
        ->and($w->to_address)->toBe('TdestAddr');
});

it('renders the transactions history page', function () {
    $user = User::factory()->create();
    creditUser($user, $this->asset, '1000000');

    actingAs($user)->get(route('transactions'))->assertOk();
});
