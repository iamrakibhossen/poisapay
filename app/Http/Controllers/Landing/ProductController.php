<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Controller;
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

        return view('landing::product', [
            'slug' => $product,
            'product' => $products[$product],
        ]);
    }
}
