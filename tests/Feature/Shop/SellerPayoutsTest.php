<?php

declare(strict_types=1);

use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Seller;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->usdt = testAsset('USDT', 6, 'tron');

    $this->user = User::factory()->create();
    Seller::create([
        'user_id' => $this->user->id, 'status' => SellerStatus::Approved,
        'categories' => [], 'settlement_asset_id' => $this->usdt->id,
    ]);
});

it('shows the withdrawable balance and reuses the wallet withdraw flow', function () {
    creditUser($this->user, $this->usdt, '25000000'); // 25 USDT spendable

    actingAs($this->user)->get(route('shop.earnings'))
        ->assertOk()
        ->assertSee('Withdrawable now')
        ->assertSee('25.00')
        ->assertSee(route('withdraw.index'), false); // the Withdraw CTA points at the wallet flow
});

it('lists the seller’s payouts in the settlement asset', function () {
    Withdrawal::create([
        'user_id' => $this->user->id, 'asset_id' => $this->usdt->id,
        'to_address' => 'TXsomeaddress', 'amount' => '5000000', 'fee' => '50000',
        'status' => WithdrawalStatus::Completed, 'idempotency_key' => 'w-1',
    ]);

    actingAs($this->user)->get(route('shop.earnings'))
        ->assertOk()
        ->assertSee('Payouts')
        ->assertSee('Completed');
});

it('excludes payouts in other assets', function () {
    $eur = testAsset('EURC', 6, 'ethereum');
    Withdrawal::create([
        'user_id' => $this->user->id, 'asset_id' => $eur->id,
        'to_address' => 'x', 'amount' => '1000000', 'fee' => '0',
        'status' => WithdrawalStatus::Completed, 'idempotency_key' => 'w-eur',
    ]);

    // No USDT payouts → the Payouts section is not rendered.
    actingAs($this->user)->get(route('shop.earnings'))
        ->assertOk()
        ->assertDontSee('Payouts');
});
