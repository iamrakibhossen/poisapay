<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');
    $this->sellerUser = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->sellerUser->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Rahim Studios', 'categories' => [],
    ]);
});

it('uploads a store logo and stores it on the public disk', function () {
    Storage::fake('public');

    $this->actingAs($this->sellerUser)
        ->post(route('shop.logo'), ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
        ->assertRedirect(route('shop'));

    $seller = $this->seller->fresh();
    expect($seller->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($seller->logo_path);
    expect($seller->logoUrl())->toContain($seller->logo_path);
});

it('rejects a non-image and oversized upload', function () {
    Storage::fake('public');

    $this->actingAs($this->sellerUser)
        ->post(route('shop.logo'), ['logo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('logo');

    expect($this->seller->fresh()->logo_path)->toBeNull();
});

it('removes the logo and deletes the file', function () {
    Storage::fake('public');
    $this->actingAs($this->sellerUser)->post(route('shop.logo'), ['logo' => UploadedFile::fake()->image('l.png')]);
    $path = $this->seller->fresh()->logo_path;

    $this->actingAs($this->sellerUser)->delete(route('shop.logo.delete'))->assertRedirect(route('shop'));

    expect($this->seller->fresh()->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('renders the logo in the public storefront header', function () {
    Storage::fake('public');
    $this->seller->update(['logo_path' => 'sell/logos/brand.png']);
    Storage::disk('public')->put('sell/logos/brand.png', 'x');

    $product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital, 'name' => 'Kit',
        'slug' => 'kit', 'status' => ProductStatus::Published, 'price_amount' => 10_000000, 'price_asset_id' => $this->asset->id,
    ]);
    SalesPage::create([
        'seller_id' => $this->seller->id, 'product_id' => $product->id, 'name' => 'Main', 'slug' => 'kit-main',
        'status' => SalesPageStatus::Published, 'sections' => [], 'theme' => [], 'version' => 1, 'published_at' => now(),
    ]);

    $this->get('/p/kit-main')->assertOk()->assertSee('sell/logos/brand.png', false);
});
