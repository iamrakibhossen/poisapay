<?php

declare(strict_types=1);

use App\Models\User;
use App\Shop\Enums\MediaStatus;
use App\Shop\Enums\SellerStatus;
use App\Shop\Models\Seller;
use App\Shop\Models\ShopMedia;
use App\Shop\Services\Media\MediaUploadService;
use App\Shop\Services\Media\MediaUrlService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    updateSetting('shop_enabled', true);
    Storage::fake('public');
    $this->user = User::factory()->create();
    $this->seller = Seller::create([
        'user_id' => $this->user->id, 'status' => SellerStatus::Approved,
        'brand_name' => 'Pixel Co', 'categories' => [],
    ]);
});

it('uploads an image, stores the original, and generates responsive + webp variants', function () {
    $this->actingAs($this->user)
        ->postJson(route('shop.media.store'), ['files' => [UploadedFile::fake()->image('hero.png', 1200, 800)]])
        ->assertCreated()
        ->assertJsonPath('items.0.name', 'hero.png');

    $media = ShopMedia::where('seller_id', $this->seller->id)->firstOrFail();

    expect($media->status)->toBe(MediaStatus::Ready)
        ->and($media->width)->toBe(1200)
        ->and($media->height)->toBe(800)
        ->and($media->path)->toStartWith('media/'.$this->seller->id)
        ->and($media->variants)->toHaveKeys(['thumb', 'thumb_webp', 'medium', 'medium_webp']);

    Storage::disk('public')->assertExists($media->path);
    Storage::disk('public')->assertExists($media->variants['thumb']['path']);
    Storage::disk('public')->assertExists($media->variants['thumb_webp']['path']);
    expect($media->variants['thumb']['width'])->toBeLessThanOrEqual(400)
        ->and($media->variants['thumb_webp']['mime'])->toBe('image/webp');
});

it('deduplicates identical uploads per seller (checksum)', function () {
    $file = UploadedFile::fake()->image('dup.png', 200, 200);
    $binary = (string) file_get_contents($file->getRealPath());

    $uploads = app(MediaUploadService::class);
    $a = $uploads->upload($this->seller, $file);
    $b = $uploads->upload($this->seller, UploadedFile::fake()->createWithContent('again.png', $binary));

    expect($a->id)->toBe($b->id)
        ->and(ShopMedia::where('seller_id', $this->seller->id)->count())->toBe(1);
});

it('rejects a non-image upload', function () {
    $this->actingAs($this->user)
        ->postJson(route('shop.media.store'), ['files' => [UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf')]])
        ->assertStatus(422);

    expect(ShopMedia::where('seller_id', $this->seller->id)->exists())->toBeFalse();
});

it('lists media scoped to the seller with name search', function () {
    $uploads = app(MediaUploadService::class);
    $uploads->upload($this->seller, UploadedFile::fake()->image('banner.png', 100, 100))->update(['name' => 'Blue banner']);
    $uploads->upload($this->seller, UploadedFile::fake()->image('logo.png', 120, 90))->update(['name' => 'Round logo']);

    $this->actingAs($this->user)->getJson(route('shop.media.items').'?q=banner')
        ->assertOk()
        ->assertJsonCount(1, 'items')
        ->assertJsonPath('items.0.name', 'Blue banner');
});

it('renames an image and edits alt text', function () {
    $media = app(MediaUploadService::class)->upload($this->seller, UploadedFile::fake()->image('x.png'));

    $this->actingAs($this->user)
        ->patchJson(route('shop.media.update', $media->id), ['name' => 'Renamed', 'alt' => 'A blue box'])
        ->assertOk()
        ->assertJsonPath('item.name', 'Renamed');

    expect($media->fresh()->alt)->toBe('A blue box');
});

it('replaces an image in place keeping the same permanent URL', function () {
    $media = app(MediaUploadService::class)->upload($this->seller, UploadedFile::fake()->image('orig.png', 300, 300));
    $url = $media->url();

    $this->actingAs($this->user)
        ->post(route('shop.media.replace', $media->id), ['file' => UploadedFile::fake()->image('new.png', 500, 500)])
        ->assertOk();

    $fresh = $media->fresh();
    expect($fresh->url())->toBe($url)          // URL/path preserved
        ->and($fresh->width)->toBe(500);        // new bytes reflected
});

it('soft deletes, restores, and permanently purges media', function () {
    $media = app(MediaUploadService::class)->upload($this->seller, UploadedFile::fake()->image('t.png'));
    $path = $media->path;

    // Soft delete → hidden from default list, file retained.
    $this->actingAs($this->user)->deleteJson(route('shop.media.destroy', $media->id))->assertOk();
    expect($this->seller->media()->count())->toBe(0)
        ->and($this->seller->media()->onlyTrashed()->count())->toBe(1);
    Storage::disk('public')->assertExists($path);

    // Restore.
    $this->actingAs($this->user)->postJson(route('shop.media.restore', $media->id))->assertOk();
    expect($this->seller->media()->count())->toBe(1);

    // Purge (force) → row + file gone.
    $this->actingAs($this->user)->deleteJson(route('shop.media.destroy', $media->id).'?force=1')->assertOk();
    expect(ShopMedia::withTrashed()->whereKey($media->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($path);
});

it('renders responsive srcset + webp for a library URL, plain img for external', function () {
    $media = app(MediaUploadService::class)->upload($this->seller, UploadedFile::fake()->image('r.png', 1600, 1200));
    $urls = app(MediaUrlService::class);

    $html = (string) $urls->img($media->url(), ['class' => 'w-full'], '100vw');
    expect($html)->toContain('<picture')
        ->and($html)->toContain('type="image/webp"')
        ->and($html)->toContain('srcset=')
        ->and($html)->toContain('loading="lazy"')
        ->and($html)->toContain('class="w-full"');

    // Legacy/external URL is untouched → backward compatible.
    $ext = (string) $urls->img('https://cdn.example.com/pic.jpg', ['class' => 'x']);
    expect($ext)->toContain('src="https://cdn.example.com/pic.jpg"')
        ->and($ext)->not->toContain('<picture');
});

it('renders the standalone media library page for the merchant', function () {
    $this->actingAs($this->user)->get(route('shop.media'))
        ->assertOk()
        ->assertSee('Media Library');
});

it('forbids acting on another seller’s media', function () {
    $media = app(MediaUploadService::class)->upload($this->seller, UploadedFile::fake()->image('mine.png'));

    $intruder = User::factory()->create();
    Seller::create(['user_id' => $intruder->id, 'status' => SellerStatus::Approved, 'brand_name' => 'Other', 'categories' => []]);

    $this->actingAs($intruder)->patchJson(route('shop.media.update', $media->id), ['name' => 'hax'])->assertForbidden();
    $this->actingAs($intruder)->deleteJson(route('shop.media.destroy', $media->id))->assertForbidden();
});
