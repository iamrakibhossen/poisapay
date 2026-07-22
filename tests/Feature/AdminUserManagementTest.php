<?php

declare(strict_types=1);

use App\Domain\Ledger\LedgerService;
use App\Enums\KycStatus;
use App\Enums\KycTier;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);

    $this->operator = Admin::create([
        'name' => 'Op', 'email' => 'op@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->operator->syncRoles(['super-admin']);
});

it('shows the user detail page with balances', function () {
    $asset = testAsset();
    $user = User::factory()->create(['name' => 'Jane Doe']);
    creditUser($user, $asset, '5000000'); // 5 USDT

    actingAs($this->operator, 'admin')
        ->get(route('admin.users.show', $user))
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertSee('USDT');
});

it('updates a user profile and KYC from the edit form', function () {
    $user = User::factory()->create(['kyc_tier' => KycTier::Unverified, 'kyc_status' => KycStatus::None]);

    actingAs($this->operator, 'admin')
        ->put(route('admin.users.update', $user), [
            'name' => 'Renamed User',
            'email' => 'renamed@poisapay.test',
            'phone' => '01700000000',
            'handle' => 'renamed',
            'base_currency' => 'usd',
            'kyc_tier' => KycTier::Full->value,
            'kyc_status' => KycStatus::Approved->value,
            'email_verified' => '1',
        ])
        ->assertRedirect(route('admin.users.show', $user));

    $user->refresh();
    expect($user->name)->toBe('Renamed User')
        ->and($user->base_currency)->toBe('USD')
        ->and($user->kyc_tier)->toBe(KycTier::Full)
        ->and($user->kyc_status)->toBe(KycStatus::Approved)
        ->and($user->email_verified_at)->not->toBeNull();
});

it('credits a user balance through the ledger', function () {
    $asset = testAsset();
    $user = User::factory()->create();

    actingAs($this->operator, 'admin')
        ->post(route('admin.users.balance', $user), [
            'asset_id' => $asset->id,
            'type' => 'credit',
            'amount' => '2.5',
            'reason' => 'Goodwill credit',
        ])
        ->assertRedirect();

    $balance = app(LedgerService::class)->availableBalance($user, $asset->id);
    expect($balance->baseString())->toBe('2500000'); // 2.5 USDT @ 6 decimals
});

it('debits a user balance and blocks overdrawing', function () {
    $asset = testAsset();
    $user = User::factory()->create();
    creditUser($user, $asset, '3000000'); // 3 USDT

    actingAs($this->operator, 'admin')
        ->post(route('admin.users.balance', $user), [
            'asset_id' => $asset->id, 'type' => 'debit', 'amount' => '1', 'reason' => 'Correction',
        ])
        ->assertRedirect();

    expect(app(LedgerService::class)->availableBalance($user, $asset->id)->baseString())->toBe('2000000');

    // Overdraw is rejected — balance unchanged.
    actingAs($this->operator, 'admin')
        ->post(route('admin.users.balance', $user), [
            'asset_id' => $asset->id, 'type' => 'debit', 'amount' => '999', 'reason' => 'Too much',
        ])
        ->assertSessionHasErrors('amount');

    expect(app(LedgerService::class)->availableBalance($user, $asset->id)->baseString())->toBe('2000000');
});

it('freezes and unfreezes a user', function () {
    $user = User::factory()->create(['is_frozen' => false]);

    actingAs($this->operator, 'admin')->post(route('admin.users.freeze', $user->id))->assertRedirect();
    expect($user->fresh()->is_frozen)->toBeTrue();

    actingAs($this->operator, 'admin')->post(route('admin.users.freeze', $user->id))->assertRedirect();
    expect($user->fresh()->is_frozen)->toBeFalse();
});

it('forbids an operator without user permissions', function () {
    $weak = Admin::create([
        'name' => 'Weak', 'email' => 'weak@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);

    actingAs($weak, 'admin')->get(route('admin.users.show', User::factory()->create()))->assertForbidden();
});
