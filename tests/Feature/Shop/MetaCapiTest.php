<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\DTOs\CheckoutData;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Jobs\SendMetaCapiPurchase;
use App\Shop\Models\Order;
use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use App\Shop\Tracking\Capi\MetaPurchaseEvent;
use App\Shop\Tracking\Contracts\MetaCapiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    $this->asset = testAsset('USDT', 6, 'tron');

    $seller = Seller::create([
        'user_id' => User::factory()->create()->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Acme', 'categories' => [],
    ]);
    $this->product = Product::create([
        'seller_id' => $seller->id, 'type' => ProductType::Digital, 'name' => 'LaunchKit',
        'slug' => 'launchkit', 'status' => ProductStatus::Published,
        'price_amount' => 4900, 'price_asset_id' => $this->asset->id,
    ]);
    $this->page = SalesPage::create([
        'seller_id' => $seller->id, 'product_id' => $this->product->id, 'name' => 'Main',
        'slug' => 'launchkit-main', 'status' => SalesPageStatus::Published, 'version' => 1,
        'published_at' => now(), 'sections' => [], 'theme' => [],
    ]);

    $this->buyer = User::factory()->create(['email' => 'BUYER@Example.com']);
    creditUser($this->buyer, $this->asset, '1000000');
});

function placeOrder($buyer, $product, ?string $salesPageId = null): Order
{
    return app(PlaceOrder::class)->execute($buyer, CheckoutData::fromArray([
        'product_id' => $product->id, 'quantity' => 1, 'idempotency_key' => 'capi-'.uniqid(),
        'sales_page_id' => $salesPageId,
    ]));
}

it('queues the server-side Purchase only when the page has a CAPI token', function () {
    Queue::fake();

    // No tracking config → no CAPI job.
    placeOrder($this->buyer, $this->product);
    Queue::assertNotPushed(SendMetaCapiPurchase::class);

    // Pixel but no access token → still no CAPI (browser pixel only).
    $this->page->update(['tracking' => ['meta' => ['enabled' => true, 'pixel_id' => '123456789012345']]]);
    placeOrder($this->buyer, $this->product);
    Queue::assertNotPushed(SendMetaCapiPurchase::class);

    // Pixel + access token → queued.
    $this->page->update(['tracking' => ['meta' => [
        'enabled' => true, 'pixel_id' => '123456789012345', 'access_token' => 'EAAG-secret',
    ]]]);
    placeOrder($this->buyer, $this->product, $this->page->id);
    Queue::assertPushed(SendMetaCapiPurchase::class, 1);
});

it('builds a deduped, PII-hashed Purchase payload', function () {
    $order = placeOrder($this->buyer, $this->product);

    $event = MetaPurchaseEvent::build($order->fresh(), ['ip' => '1.2.3.4', 'ua' => 'UA', 'url' => 'https://x/y']);

    expect($event['event_name'])->toBe('Purchase')
        ->and($event['event_id'])->toBe((string) $order->getKey())          // dedup key = order id
        ->and($event['action_source'])->toBe('website')
        ->and($event['user_data']['em'][0])->toBe(hash('sha256', 'buyer@example.com')) // lower+trim+sha256
        ->and($event['user_data']['client_ip_address'])->toBe('1.2.3.4')
        ->and($event['custom_data']['currency'])->toBe('USDT')
        ->and($event['custom_data']['value'])->toBe(0.0049);
});

it('the http driver posts a batch to the pixel events endpoint', function () {
    config()->set('shop.tracking.meta_capi.driver', 'http');
    Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1], 200)]);

    $this->page->update(['tracking' => ['meta' => [
        'enabled' => true, 'pixel_id' => '123456789012345', 'access_token' => 'EAAG-secret',
    ]]]);

    $order = placeOrder($this->buyer, $this->product);
    (new SendMetaCapiPurchase((string) $order->id, '123456789012345', 'EAAG-secret'))->handle(app(MetaCapiClient::class));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/123456789012345/events')
            && $request['access_token'] === 'EAAG-secret'
            && $request['data'][0]['event_name'] === 'Purchase';
    });
});
