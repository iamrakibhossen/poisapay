<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
use App\Support\Seo\JsonLd;
use App\Support\Seo\SeoData;
use Illuminate\Contracts\View\View;

/**
 * Public product marketing pages (linked from the landing nav + footer). Each
 * product is a config entry (config/landing.php `products`) rendered by one
 * shared template.
 */
final class ProductController extends Controller
{
    public function show(string $product): View
    {
        /** @var array<string, array<string, mixed>> $products */
        $products = config('landing.products', []);
        abort_unless(isset($products[$product]), 404);

        $data = $products[$product];
        $url = route('products.show', $product);

        $seo = SeoData::make((string) $data['title'], (string) ($data['lead'] ?? ''))
            ->withCanonical($url)
            ->withType('product')
            ->withBreadcrumbs([
                ['name' => 'Home', 'url' => route('home')],
                ['name' => (string) ($data['eyebrow'] ?? $data['title']), 'url' => $url],
            ])
            ->withSchema(JsonLd::product([
                'name' => (string) $data['title'],
                'description' => (string) ($data['lead'] ?? ''),
                'image' => asset((string) config('seo.default_image')),
                'url' => $url,
            ]));

        if (! empty($data['faqs'])) {
            $seo = $seo->withSchema(JsonLd::faq(array_map(
                static fn (array $f) => ['question' => (string) $f['q'], 'answer' => (string) $f['a']],
                $data['faqs'],
            )));
        }

        return view('landing::product', [
            'slug' => $product,
            'product' => $data,
            'seo' => $seo,
        ]);
    }
}
