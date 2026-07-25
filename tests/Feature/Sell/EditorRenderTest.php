<?php
use App\Models\User;
use App\Sell\Enums\ProductStatus;
use App\Sell\Enums\ProductType;
use App\Sell\Enums\SalesPageStatus;
use App\Sell\Enums\SellerStatus;
use App\Sell\Models\Product;
use App\Sell\Models\SalesPage;
use App\Sell\Models\Seller;

it('renders the improved sales-page editor', function () {
    updateSetting('sell_enabled', true);
    $asset = testAsset('USDT', 6, 'tron');
    $u = User::factory()->create();
    $seller = Seller::create(['user_id' => $u->id, 'status' => SellerStatus::Approved, 'brand_name' => 'Rakib', 'categories' => []]);
    $p = Product::create(['seller_id' => $seller->id, 'type' => ProductType::Digital, 'name' => 'Kit', 'slug' => 'kit', 'status' => ProductStatus::Published, 'price_amount' => 4900000, 'price_asset_id' => $asset->id]);
    // old-format persisted page: faq as strings, testimonials as {name,quote}
    $page = SalesPage::create([
        'seller_id' => $seller->id, 'product_id' => $p->id, 'name' => 'Main', 'slug' => 'rakib-hossen-2',
        'status' => SalesPageStatus::Draft, 'version' => 1,
        'theme' => ['accent' => '#059669', 'btn' => 'pill', 'font' => 'Inter'],
        'sections' => [
            ['type' => 'hero', 'enabled' => true, 'content' => ['headline' => 'Hi']],
            ['type' => 'faq', 'enabled' => true, 'content' => ['Old question one?', 'Old question two?']],
            ['type' => 'testimonials', 'enabled' => true, 'content' => [['name' => 'A', 'quote' => 'Great']]],
        ],
    ]);

    $this->actingAs($u)->get(route('sell.sales-page.edit', ['slug' => 'rakib-hossen-2']))
        ->assertOk()
        ->assertSee('Add section')
        ->assertSee('How it works')   // new section available in catalog
        ->assertSee('Old question one?'); // old faq normalized + still present
});
