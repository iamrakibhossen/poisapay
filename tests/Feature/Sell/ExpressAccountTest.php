<?php

declare(strict_types=1);

use App\Models\User;
use App\Sell\Enums\ProductStatus;
use App\Sell\Enums\ProductType;
use App\Sell\Enums\SalesPageStatus;
use App\Sell\Enums\SellerStatus;
use App\Sell\Models\Product;
use App\Sell\Models\SalesPage;
use App\Sell\Models\Seller;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    updateSetting('sell_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');

    $this->seller = Seller::create([
        'user_id' => User::factory()->create()->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Rahim', 'categories' => [],
    ]);
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital, 'name' => 'Kit',
        'slug' => 'kit', 'status' => ProductStatus::Published, 'price_amount' => 10_000000, 'price_asset_id' => $this->asset->id,
    ]);
    SalesPage::create([
        'seller_id' => $this->seller->id, 'product_id' => $this->product->id, 'name' => 'Main', 'slug' => 'kit-main',
        'status' => SalesPageStatus::Published, 'sections' => [], 'theme' => [], 'version' => 1, 'published_at' => now(),
    ]);
});

it('sends a guest who clicks Buy to the on-funnel account step, not the app login', function () {
    $this->post('/p/kit-main/checkout')
        ->assertRedirect(route('funnel.account', ['slug' => 'kit-main']));
});

it('shows the account step and gates it behind a published page', function () {
    $this->get('/p/kit-main/account')->assertOk()->assertSee('Almost there');
    $this->get('/p/nope/account')->assertNotFound();
});

it('creates an account inline and resumes at the pay page', function () {
    // Simulate arriving from Buy (sets the intended step).
    $this->post('/p/kit-main/checkout');

    $this->post('/p/kit-main/account', [
        'mode' => 'new', 'name' => 'New Buyer', 'email' => 'buyer@example.com', 'password' => 'supersecret',
    ])->assertRedirect(route('funnel.pay', ['slug' => 'kit-main']));

    $this->assertAuthenticated();
    expect(User::where('email', 'buyer@example.com')->exists())->toBeTrue();
});

it('signs an existing buyer in from the funnel', function () {
    User::factory()->create(['email' => 'me@example.com', 'password' => Hash::make('password123')]);

    $this->post('/p/kit-main/account', ['mode' => 'existing', 'email' => 'me@example.com', 'password' => 'password123'])
        ->assertRedirect(route('funnel.pay', ['slug' => 'kit-main']));

    $this->assertAuthenticated();
});

it('rejects wrong credentials and a duplicate email', function () {
    User::factory()->create(['email' => 'taken@example.com', 'password' => Hash::make('password123')]);

    // wrong password
    $this->post('/p/kit-main/account', ['mode' => 'existing', 'email' => 'taken@example.com', 'password' => 'nope'])
        ->assertSessionHasErrors('password');
    $this->assertGuest();

    // duplicate email on create
    $this->post('/p/kit-main/account', ['mode' => 'new', 'name' => 'X', 'email' => 'taken@example.com', 'password' => 'supersecret'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('redirects an already signed-in user straight past the account step', function () {
    $this->actingAs(User::factory()->create())
        ->get('/p/kit-main/account')
        ->assertRedirect(route('funnel.pay', ['slug' => 'kit-main']));
});
