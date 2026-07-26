<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Dynamic XML sitemaps for the public surface — a sitemap index plus child
 * sitemaps (core routes, CMS pages, product pages) with image entries. Only
 * public/indexable URLs are listed. Cached 1h. Isolated from business logic.
 */
final class SitemapController extends Controller
{
    private const TTL = 3600;

    /** Sitemap index → child sitemaps. */
    public function index(): Response
    {
        $children = ['core', 'pages', 'products'];
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($children as $c) {
            $xml .= '<sitemap><loc>'.e(url("/sitemap-{$c}.xml")).'</loc></sitemap>';
        }
        $xml .= '</sitemapindex>';

        return $this->xml($xml);
    }

    /** Static public routes. */
    public function core(): Response
    {
        return $this->xml(Cache::remember('sitemap:core', self::TTL, function () {
            $urls = [
                ['loc' => route('home'), 'priority' => '1.0', 'freq' => 'daily'],
                ['loc' => route('help-center'), 'priority' => '0.7', 'freq' => 'weekly'],
                ['loc' => route('marketing.prices'), 'priority' => '0.7', 'freq' => 'hourly'],
                ['loc' => route('status'), 'priority' => '0.4', 'freq' => 'daily'],
            ];

            return $this->urlset($urls);
        }));
    }

    /** Published CMS pages. */
    public function pages(): Response
    {
        return $this->xml(Cache::remember('sitemap:pages', self::TTL, function () {
            $urls = Page::query()->published()->get()->map(fn (Page $p) => [
                'loc' => route('page.show', $p->slug),
                'lastmod' => optional($p->updated_at)->toAtomString(),
                'priority' => '0.6',
                'freq' => 'monthly',
            ])->all();

            return $this->urlset($urls);
        }));
    }

    /** Product marketing pages (each carries the brand OG image). */
    public function products(): Response
    {
        return $this->xml(Cache::remember('sitemap:products', self::TTL, function () {
            $image = asset((string) config('seo.default_image'));
            /** @var array<string, array<string, mixed>> $products */
            $products = config('landing.products', []);
            $urls = collect($products)->map(fn ($data, $slug) => [
                'loc' => route('products.show', $slug),
                'priority' => '0.8',
                'freq' => 'weekly',
                'image' => $image,
                'image_title' => (string) ($data['title'] ?? ''),
            ])->values()->all();

            return $this->urlset($urls);
        }));
    }

    /**
     * @param  list<array<string,mixed>>  $urls
     */
    private function urlset(array $urls): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
            .'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';
        foreach ($urls as $u) {
            $xml .= '<url><loc>'.e($u['loc']).'</loc>';
            if (! empty($u['lastmod'])) {
                $xml .= '<lastmod>'.e($u['lastmod']).'</lastmod>';
            }
            $xml .= '<changefreq>'.($u['freq'] ?? 'weekly').'</changefreq>';
            $xml .= '<priority>'.($u['priority'] ?? '0.5').'</priority>';
            if (! empty($u['image'])) {
                $xml .= '<image:image><image:loc>'.e($u['image']).'</image:loc>';
                if (! empty($u['image_title'])) {
                    $xml .= '<image:title>'.e($u['image_title']).'</image:title>';
                }
                $xml .= '</image:image>';
            }
            $xml .= '</url>';
        }

        return $xml.'</urlset>';
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
