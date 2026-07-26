<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Seller;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    Artisan::call('db:seed', ['--class' => 'ShopNotificationTemplateSeeder', '--force' => true]);
    $this->usdt = testAsset('USDT', 6, 'tron');
});

function approvedSeller(): User
{
    $user = User::factory()->create();
    Seller::create([
        'user_id' => $user->id, 'status' => SellerStatus::Approved,
        'categories' => [], 'settlement_asset_id' => test()->usdt->id,
    ]);

    return $user;
}

it('alerts a seller at or below the threshold', function () {
    $user = approvedSeller(); // available = 0, default threshold = 0 → alert

    Artisan::call('shop:low-balance-alerts');

    expect($user->notifications()->get()->pluck('data.title'))->toContain('Low balance');
});

it('does not alert a seller with a healthy balance', function () {
    $user = approvedSeller();
    creditUser($user, $this->usdt, '10000000'); // 10 USDT, threshold 0

    Artisan::call('shop:low-balance-alerts');

    expect($user->notifications()->count())->toBe(0);
});

it('respects the configurable threshold', function () {
    updateSetting('shop_low_balance_threshold', 5); // alert at/below 5 USDT
    $low = approvedSeller();
    creditUser($low, $this->usdt, '3000000'); // 3 USDT → alert
    $ok = approvedSeller();
    creditUser($ok, $this->usdt, '10000000'); // 10 USDT → healthy

    Artisan::call('shop:low-balance-alerts');

    expect($low->notifications()->count())->toBe(1)
        ->and($ok->notifications()->count())->toBe(0);
});

it('is idempotent per day', function () {
    $user = approvedSeller();

    Artisan::call('shop:low-balance-alerts');
    Artisan::call('shop:low-balance-alerts'); // same day → no second alert

    expect($user->notifications()->count())->toBe(1);
});
