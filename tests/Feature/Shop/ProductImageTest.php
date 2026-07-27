<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Product;
use App\Shop\Models\Seller;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->asset = fiatAsset('USD', 2); // Shop prices are fiat-only
    $this->user = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->user->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Cover Studios', 'categories' => [],
    ]);
});

it('uploads a product cover image and stores it on the public disk', function () {
    Storage::fake('public');

    $this->actingAs($this->user)->post(route('shop.products.store'), [
        'name' => 'Imaged Kit', 'type' => 'digital', 'price' => '19', 'price_asset_id' => $this->asset->id,
        'image' => UploadedFile::fake()->image('cover.png', 800, 600),
    ])->assertRedirect(route('shop.products'));

    $product = Product::where('seller_id', $this->seller->id)->firstOrFail();
    expect($product->image)->not->toBeNull();
    Storage::disk('public')->assertExists($product->image);
});

it('rejects a non-image product cover upload', function () {
    Storage::fake('public');

    $this->actingAs($this->user)->post(route('shop.products.store'), [
        'name' => 'Bad Cover', 'type' => 'digital', 'price' => '19', 'price_asset_id' => $this->asset->id,
        'image' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('image');

    expect(Product::where('seller_id', $this->seller->id)->exists())->toBeFalse();
});

it('keeps the existing cover on edit when no new file is uploaded', function () {
    Storage::fake('public');

    $this->actingAs($this->user)->post(route('shop.products.store'), [
        'name' => 'Keeps Cover', 'type' => 'digital', 'price' => '19', 'price_asset_id' => $this->asset->id,
        'image' => UploadedFile::fake()->image('cover.png'),
    ]);
    $product = Product::where('seller_id', $this->seller->id)->firstOrFail();
    $original = $product->image;

    $this->actingAs($this->user)->put(route('shop.products.update', $product->id), [
        'name' => 'Keeps Cover Renamed', 'type' => 'digital', 'price' => '19', 'price_asset_id' => $this->asset->id,
    ])->assertRedirect(route('shop.products'));

    expect($product->fresh()->image)->toBe($original)
        ->and($product->fresh()->name)->toBe('Keeps Cover Renamed');
});
