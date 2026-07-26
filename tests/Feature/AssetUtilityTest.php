<?php

declare(strict_types=1);

use App\Utilities\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function uploadRequest(string $field, UploadedFile $file): Request
{
    return Request::create('/', 'POST', [], [], [$field => $file]);
}

it('stores an upload on the given disk under a date-bucketed path', function () {
    Storage::fake('public');

    $path = Asset::store(uploadRequest('logo', UploadedFile::fake()->image('logo.png')), 'logo', null, 'shop/logos', 'public');

    expect($path)->toStartWith('uploads/shop/logos/')
        ->and($path)->toEndWith('.png')
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

it('keeps the old path when the request carries no file', function () {
    expect(Asset::store(Request::create('/', 'POST'), 'logo', 'existing/path.png'))->toBe('existing/path.png');
});

it('removes a file only from the given disk', function () {
    Storage::fake('public');
    $path = Asset::store(uploadRequest('a', UploadedFile::fake()->image('a.jpg')), 'a', null, 'x', 'public');

    expect(Asset::removeFile($path, 'public'))->toBeTrue()
        ->and(Storage::disk('public')->exists($path))->toBeFalse()
        ->and(Asset::removeFile(null, 'public'))->toBeFalse()
        ->and(Asset::removeFile('missing/file.png', 'public'))->toBeFalse();
});

it('resolves URLs, passing through absolute and root-relative paths', function () {
    Storage::fake('public');

    expect(Asset::url(null))->toBeNull()
        ->and(Asset::url(''))->toBeNull()
        ->and(Asset::url('https://cdn.example.com/x.png'))->toBe('https://cdn.example.com/x.png')
        ->and(Asset::url('/img/inline.svg'))->toBe('/img/inline.svg')
        ->and(Asset::url('uploads/x.png', 'public'))->toContain('uploads/x.png');
});
