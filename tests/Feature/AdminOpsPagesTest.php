<?php

declare(strict_types=1);

use App\Domain\Deposit\SubmitManualDepositAction;
use App\Domain\Ledger\LedgerService;
use App\Enums\CardStatus;
use App\Enums\DepositStatus;
use App\Enums\KycTier;
use App\Enums\MerchantStatus;
use App\Models\Admin;
use App\Models\Card;
use App\Models\DepositMethod;
use App\Models\Merchant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'RegistrySeeder', '--force' => true]);

    $this->asset = testAsset('USDT', 6, 'tron');
    $this->ledger = app(LedgerService::class);

    // A full-permission operator on the admin guard.
    $this->operator = Admin::create([
        'name' => 'Op', 'email' => 'op@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $this->operator->syncRoles(['super-admin']);
});

/** A consumer account with a card + merchant so the ops pages render with data. */
function opsCustomer(): User
{
    $customer = User::factory()->create(['kyc_tier' => KycTier::Full]);
    Card::create([
        'user_id' => $customer->id, 'program' => 'poisapay-demo', 'type' => 'virtual', 'network' => 'visa',
        'issuer_card_ref' => 'tok_'.str_repeat('c', 24), 'last4' => '4242', 'status' => CardStatus::Active, 'settlement_currency' => 'USD',
    ]);

    return $customer;
}

it('loads every converted ops page for a permitted operator', function () {
    opsCustomer();
    Merchant::create([
        'user_id' => User::factory()->create(['kyc_tier' => KycTier::Full])->id,
        'business_name' => 'Ops Store', 'slug' => 'ops-store', 'category' => 'retail', 'status' => 'active', 'approved_at' => now(),
    ]);

    foreach (['deposits', 'users', 'cards', 'card-disputes', 'merchants'] as $route) {
        actingAs($this->operator, 'admin')->get(route("admin.{$route}"))->assertOk();
    }
});

it('approves a manual deposit and credits the ledger', function () {
    $customer = opsCustomer();
    $method = DepositMethod::create([
        'asset_id' => $this->asset->id, 'name' => 'Test Bank', 'type' => 'bank',
        'details' => ['bank_name' => 'City Bank'], 'min_amount' => '1000000', 'is_active' => true, 'sort' => 0,
    ]);

    $deposit = app(SubmitManualDepositAction::class)->execute(
        $customer, $method, Money::ofBase('5000000', 6, 'USDT'), 'TXN-OPS-1'
    );

    expect($this->ledger->availableBalance($customer, $this->asset->id)->baseString())->toBe('0');

    actingAs($this->operator, 'admin')
        ->post(route('admin.deposits.approve', $deposit->id))
        ->assertRedirect();

    expect($deposit->fresh()->status)->toBe(DepositStatus::Credited)
        ->and($this->ledger->availableBalance($customer, $this->asset->id)->baseString())->toBe('5000000');
});

it('freezes and unfreezes a user', function () {
    $customer = opsCustomer();
    expect($customer->is_frozen)->toBeFalsy();

    actingAs($this->operator, 'admin')
        ->post(route('admin.users.freeze', $customer->id))
        ->assertRedirect();
    expect($customer->fresh()->is_frozen)->toBeTrue();

    actingAs($this->operator, 'admin')
        ->post(route('admin.users.freeze', $customer->id))
        ->assertRedirect();
    expect($customer->fresh()->is_frozen)->toBeFalse();
});

it('freezes and unfreezes a card', function () {
    $customer = opsCustomer();
    $card = $customer->cards()->first();
    expect($card->status)->toBe(CardStatus::Active);

    actingAs($this->operator, 'admin')
        ->post(route('admin.cards.freeze', $card->id))
        ->assertRedirect();
    expect($card->fresh()->status)->toBe(CardStatus::Frozen);

    actingAs($this->operator, 'admin')
        ->post(route('admin.cards.freeze', $card->id))
        ->assertRedirect();
    expect($card->fresh()->status)->toBe(CardStatus::Active);
});

it('approves then suspends a merchant, transitioning status', function () {
    $merchant = Merchant::create([
        'user_id' => User::factory()->create(['kyc_tier' => KycTier::Full])->id,
        'business_name' => 'Pending Co', 'slug' => 'pending-co', 'category' => 'retail', 'status' => MerchantStatus::Pending,
    ]);

    actingAs($this->operator, 'admin')
        ->post(route('admin.merchants.approve', $merchant->id))
        ->assertRedirect();
    expect($merchant->fresh()->status)->toBe(MerchantStatus::Active)
        ->and($merchant->fresh()->approved_at)->not->toBeNull();

    actingAs($this->operator, 'admin')
        ->post(route('admin.merchants.suspend', $merchant->id), ['suspendReason' => 'Pending compliance review'])
        ->assertRedirect();
    expect($merchant->fresh()->status)->toBe(MerchantStatus::Suspended)
        ->and($merchant->fresh()->suspension_reason)->toBe('Pending compliance review');
});

it('403s a non-permitted operator on a gated page', function () {
    // The support role lacks view-cards, so the cards console is forbidden.
    $support = Admin::create([
        'name' => 'Sup', 'email' => 'support@poisapay.test', 'password' => bcrypt('password'), 'is_active' => true,
    ]);
    $support->syncRoles(['support']);

    actingAs($support, 'admin')->get(route('admin.cards'))->assertForbidden();
});
