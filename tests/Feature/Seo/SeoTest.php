<?php

declare(strict_types=1);

use App\Models\Faq;
use App\Models\Page;

it('renders a complete, single set of meta tags on the home page', function () {
    $html = $this->get('/')->assertOk()->getContent();

    // Exactly one title + one canonical + one description (no duplicates).
    expect(substr_count($html, '<title>'))->toBe(1)
        ->and(substr_count($html, 'rel="canonical"'))->toBe(1)
        ->and(substr_count($html, 'name="description"'))->toBe(1);

    expect($html)
        ->toContain('<title>PoishaPay · Spend crypto like cash, with a premium virtual card</title>')
        ->toContain('property="og:title"')
        ->toContain('property="og:image"')
        ->toContain('name="twitter:card" content="summary_large_image"')
        ->toContain('name="robots" content="index,follow')
        ->toContain('rel="manifest"')
        ->toContain('name="theme-color"')
        ->toContain('application/ld+json');
});

it('emits a JSON-LD graph with Organization, WebSite and SoftwareApplication on home', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)
        ->toContain('"@type":"Organization"')
        ->toContain('"@type":"WebSite"')
        ->toContain('"@type":"SoftwareApplication"');
});

it('adds Product + Breadcrumb structured data to product pages', function () {
    $html = $this->get('/products/virtual-card')->assertOk()->getContent();

    expect($html)
        ->toContain('"@type":"Product"')
        ->toContain('"@type":"BreadcrumbList"')
        ->toContain('property="og:type" content="product"');
});

it('adds FAQ structured data to the help center', function () {
    Faq::query()->create([
        'question' => 'Is PoishaPay safe?', 'answer' => 'Yes, bank-grade custody.',
        'group' => 'General', 'sort_order' => 1, 'status' => 'published',
    ]);

    $html = $this->get('/help-center')->assertOk()->getContent();
    expect($html)->toContain('"@type":"FAQPage"')->toContain('Is PoishaPay safe?');
});

it('gives CMS legal pages a canonical + breadcrumb', function () {
    Page::query()->updateOrCreate(['slug' => 'terms'], [
        'title' => 'Terms of Service', 'content' => '<p>Terms.</p>', 'status' => 'published',
        'meta_description' => 'Terms of Service for PoishaPay.',
    ]);

    $html = $this->get('/pages/terms')->assertOk()->getContent();
    expect($html)
        ->toContain(url('/pages/terms'))
        ->toContain('"@type":"BreadcrumbList"')
        ->toContain('<title>Terms of Service · PoishaPay</title>');
});

it('serves a sitemap index and child sitemaps', function () {
    $this->get('/sitemap.xml')->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<sitemapindex', false)
        ->assertSee('/sitemap-pages.xml', false);

    $this->get('/sitemap-core.xml')->assertOk()->assertSee(url('/'), false);
    $this->get('/sitemap-products.xml')->assertOk()->assertSee('/products/virtual-card', false);
});

it('lists published CMS pages in the pages sitemap', function () {
    Page::query()->updateOrCreate(['slug' => 'privacy'], [
        'title' => 'Privacy', 'content' => '<p>x</p>', 'status' => 'published',
    ]);
    Page::query()->updateOrCreate(['slug' => 'secret-draft'], [
        'title' => 'Draft', 'content' => '<p>x</p>', 'status' => 'draft',
    ]);

    $xml = $this->get('/sitemap-pages.xml')->assertOk()->getContent();
    expect($xml)->toContain('/pages/privacy')->not->toContain('/pages/secret-draft');
});

it('serves robots.txt from the route (not a static file)', function () {
    $this->get('/robots.txt')->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *', false);
});

it('noindexes the auth (guest) pages', function () {
    $html = $this->get('/login')->assertOk()->getContent();
    expect($html)->toContain('name="robots" content="noindex,nofollow"');
});

it('exposes a valid web manifest', function () {
    $manifest = json_decode((string) file_get_contents(public_path('site.webmanifest')), true);
    expect($manifest['name'])->toBe('PoishaPay')
        ->and($manifest['theme_color'])->toBe('#2053DD')
        ->and($manifest['icons'])->toHaveCount(2);
});
