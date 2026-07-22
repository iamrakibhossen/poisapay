<?php

declare(strict_types=1);

use App\Domain\Ledger\AccountResolver;
use App\Domain\Ledger\LedgerService;
use App\Domain\Rewards\AwardCashbackAction;
use App\Domain\Rewards\ManualGrantAction;
use App\Domain\Rewards\ProcessReferralQualificationAction;
use App\Enums\ReferralStatus;
use App\Events\UserRegistered;
use App\Listeners\GrantWelcomeBonus;
use App\Models\Admin;
use App\Models\Asset;
use App\Models\Referral;
use App\Models\RewardCampaign;
use App\Models\RewardGrant;
use App\Models\User;
use App\Support\Money;

beforeEach(function () {
    $this->usdt = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);
    $this->user = User::factory()->create();
});

function bdtAsset(): Asset
{
    $bdt = Asset::firstOrCreate(
        ['symbol' => 'BDT', 'chain_id' => null, 'contract_address' => null],
        ['name' => 'Bangladeshi Taka', 'kind' => 'fiat', 'currency_code' => 'BDT', 'decimals' => 2, 'is_active' => true],
    );
    app(AccountResolver::class)->ensureSystemAccounts($bdt->id);

    return $bdt;
}

it('awards percentage cashback from a live campaign', function () {
    RewardCampaign::create(['key' => 'cashback', 'name' => 'CB', 'type' => 'percentage', 'rate_bps' => 50, 'is_active' => true]);

    $grant = app(AwardCashbackAction::class)->execute($this->user, $this->usdt, Money::ofBase('25000000', 6, 'USDT'), 'cb-1');

    // 0.5% of 25 USDT = 0.125 USDT.
    expect($grant)->not->toBeNull()
        ->and($grant->amount)->toBe('125000')
        ->and($grant->type)->toBe('cashback')
        ->and($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('125000');
});

it('does not award cashback when no live campaign exists', function () {
    $grant = app(AwardCashbackAction::class)->execute($this->user, $this->usdt, Money::ofBase('25000000', 6, 'USDT'), 'cb-2');

    expect($grant)->toBeNull()
        ->and(RewardGrant::count())->toBe(0);
});

it('respects the cashback minimum-spend gate', function () {
    RewardCampaign::create(['key' => 'cashback', 'name' => 'CB', 'type' => 'percentage', 'rate_bps' => 50, 'min_spend' => '50000000', 'is_active' => true]);

    $grant = app(AwardCashbackAction::class)->execute($this->user, $this->usdt, Money::ofBase('25000000', 6, 'USDT'), 'cb-3');

    expect($grant)->toBeNull();
});

it('caps cashback at the configured maximum', function () {
    RewardCampaign::create(['key' => 'cashback', 'name' => 'CB', 'type' => 'percentage', 'rate_bps' => 50, 'max_reward' => '100000', 'is_active' => true]);

    $grant = app(AwardCashbackAction::class)->execute($this->user, $this->usdt, Money::ofBase('25000000', 6, 'USDT'), 'cb-4');

    // Uncapped would be 125000; capped to 100000.
    expect($grant->amount)->toBe('100000');
});

it('ignores an inactive campaign', function () {
    RewardCampaign::create(['key' => 'cashback', 'name' => 'CB', 'type' => 'percentage', 'rate_bps' => 50, 'is_active' => false]);

    $grant = app(AwardCashbackAction::class)->execute($this->user, $this->usdt, Money::ofBase('25000000', 6, 'USDT'), 'cb-5');

    expect($grant)->toBeNull();
});

it('cashback grant is idempotent by key', function () {
    RewardCampaign::create(['key' => 'cashback', 'name' => 'CB', 'type' => 'percentage', 'rate_bps' => 50, 'is_active' => true]);
    $action = app(AwardCashbackAction::class);

    $action->execute($this->user, $this->usdt, Money::ofBase('25000000', 6, 'USDT'), 'cb-dup');
    $action->execute($this->user, $this->usdt, Money::ofBase('25000000', 6, 'USDT'), 'cb-dup');

    expect(RewardGrant::where('user_id', $this->user->id)->count())->toBe(1)
        ->and($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('125000');
});

it('grants the welcome bonus from a live campaign', function () {
    $bdt = bdtAsset();
    RewardCampaign::create(['key' => 'welcome', 'name' => 'Welcome', 'type' => 'fixed', 'asset_id' => $bdt->id, 'amount' => '7777', 'is_active' => true]);

    app(GrantWelcomeBonus::class)->handle(new UserRegistered($this->user->id));

    $grant = RewardGrant::where('user_id', $this->user->id)->where('type', 'welcome')->first();
    expect($grant)->not->toBeNull()
        ->and($grant->amount)->toBe('7777')
        ->and($this->ledger->availableBalance($this->user, $bdt->id)->baseString())->toBe('7777');
});

it('pays referral bonuses from live campaigns on qualification', function () {
    $bdt = bdtAsset();
    RewardCampaign::create(['key' => 'referral_referrer', 'name' => 'Referrer', 'type' => 'fixed', 'asset_id' => $bdt->id, 'amount' => '30000', 'is_active' => true]);
    RewardCampaign::create(['key' => 'referral_referee', 'name' => 'Referee', 'type' => 'fixed', 'asset_id' => $bdt->id, 'amount' => '15000', 'is_active' => true]);

    $referrer = $this->user;
    $referee = User::factory()->create(['referred_by' => $referrer->id]);
    $referral = Referral::create([
        'referrer_id' => $referrer->id, 'referee_id' => $referee->id, 'code' => 'ABC123', 'status' => ReferralStatus::Pending,
    ]);

    app(ProcessReferralQualificationAction::class)->execute($referral);

    expect($referral->fresh()->status)->toBe(ReferralStatus::Rewarded)
        ->and($this->ledger->availableBalance($referrer, $bdt->id)->baseString())->toBe('30000')
        ->and($this->ledger->availableBalance($referee, $bdt->id)->baseString())->toBe('15000');
});

it('lets an operator issue a manual grant', function () {
    $admin = Admin::create(['name' => 'Op', 'email' => 'op-rw@poisapay.test', 'password' => bcrypt('x'), 'is_active' => true]);

    $grant = app(ManualGrantAction::class)->execute($admin, $this->user, $this->usdt, Money::ofBase('5000000', 6, 'USDT'), 'goodwill');

    expect($grant->type)->toBe('manual')
        ->and($this->ledger->availableBalance($this->user, $this->usdt->id)->baseString())->toBe('5000000');
});
