<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Actions\Order\PlaceOrder;
use App\Shop\Contracts\FileScanner;
use App\Shop\DTOs\CheckoutData;
use App\Shop\Enums\FileScanStatus;
use App\Shop\Enums\ProductStatus;
use App\Shop\Enums\ProductType;
use App\Shop\Enums\SellerStatus;
use App\Shop\Jobs\ScanProductFile;
use App\Shop\Models\Download;
use App\Shop\Models\Product;
use App\Shop\Models\Seller;
use App\Shop\Services\Files\ProductFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    Storage::fake('local');
    $this->asset = testAsset('USDT', 6, 'tron');

    $this->sellerUser = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->sellerUser->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Acme', 'categories' => [],
    ]);
    $this->product = Product::create([
        'seller_id' => $this->seller->id, 'type' => ProductType::Digital, 'name' => 'LaunchKit',
        'slug' => 'launchkit', 'status' => ProductStatus::Published,
        'price_amount' => 4900, 'price_asset_id' => $this->asset->id,
    ]);
});

it('stores an uploaded file privately as the current version and queues a scan', function () {
    Queue::fake();

    $file = app(ProductFileService::class)->store($this->product, UploadedFile::fake()->create('kit.zip', 20, 'application/zip'));

    expect($file->scan_status)->toBe(FileScanStatus::Pending)
        ->and($file->is_current)->toBeTrue()
        ->and($file->version)->toBe('v1')
        ->and($file->checksum_sha256)->not->toBeNull()
        ->and(Storage::disk('local')->exists($file->path))->toBeTrue();

    Queue::assertPushed(ScanProductFile::class, fn ($job) => $job->productFileId === $file->id);
});

it('supersedes the previous version on re-upload', function () {
    Queue::fake();
    $svc = app(ProductFileService::class);

    $v1 = $svc->store($this->product, UploadedFile::fake()->create('a.zip', 10));
    $v2 = $svc->store($this->product, UploadedFile::fake()->create('b.zip', 10));

    expect($v2->version)->toBe('v2')
        ->and($v2->is_current)->toBeTrue()
        ->and($v1->fresh()->is_current)->toBeFalse();
});

it('marks a clean file deliverable via the scan job', function () {
    $file = app(ProductFileService::class)->store($this->product, UploadedFile::fake()->create('ok.zip', 10));

    (new ScanProductFile($file->id))->handle(app(FileScanner::class));

    expect($file->fresh()->scan_status)->toBe(FileScanStatus::Clean)
        ->and($file->fresh()->is_current)->toBeTrue();
});

it('quarantines an infected (EICAR) file — not current, not deliverable', function () {
    $eicar = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';
    $file = app(ProductFileService::class)->store($this->product, UploadedFile::fake()->createWithContent('virus.zip', $eicar));

    (new ScanProductFile($file->id))->handle(app(FileScanner::class));

    expect($file->fresh()->scan_status)->toBe(FileScanStatus::Infected)
        ->and($file->fresh()->is_current)->toBeFalse();
});

it('creating a digital product with a file stores + queues it', function () {
    Queue::fake();

    $fiat = fiatAsset('USD', 2); // Shop prices are fiat-only
    $this->actingAs($this->sellerUser)->post(route('shop.products.store'), [
        'name' => 'PDF Guide', 'type' => 'digital', 'price' => '9', 'price_asset_id' => $fiat->id,
        'file' => UploadedFile::fake()->create('guide.pdf', 30, 'application/pdf'),
    ])->assertRedirect(route('shop.products'));

    $product = Product::where('name', 'PDF Guide')->firstOrFail();
    expect($product->files()->count())->toBe(1)
        ->and($product->files()->first()->scan_status)->toBe(FileScanStatus::Pending);
    Queue::assertPushed(ScanProductFile::class);
});

it('lets the buyer download only a clean file, and blocks while pending', function () {
    Queue::fake(); // hold the scan so the file stays Pending until we clear it
    $buyer = User::factory()->create();
    creditUser($buyer, $this->asset, '1000000');

    $file = app(ProductFileService::class)->store($this->product, UploadedFile::fake()->create('kit.zip', 10));

    $order = app(PlaceOrder::class)->execute($buyer, CheckoutData::fromArray([
        'product_id' => $this->product->id, 'quantity' => 1, 'idempotency_key' => 'dl-1',
    ]));
    $item = $order->items->first();

    // Still pending → blocked.
    $this->actingAs($buyer)->get(route('purchases.download', $item->id))
        ->assertRedirect();
    expect(Download::where('order_item_id', $item->id)->first()->download_count)->toBe(0);

    // Clear the scan → downloadable, and the grant count ticks up.
    $file->update(['scan_status' => FileScanStatus::Clean]);
    $this->actingAs($buyer)->get(route('purchases.download', $item->id))->assertOk();
    expect(Download::where('order_item_id', $item->id)->first()->download_count)->toBe(1);
});
