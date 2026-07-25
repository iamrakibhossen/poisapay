<?php

declare(strict_types=1);

namespace App\Shop\Actions\SalesPage;

use App\Shop\DTOs\SalesPageData;
use App\Shop\Enums\SalesPageStatus;
use App\Shop\Exceptions\ShopException;
use App\Shop\Models\Product;
use App\Shop\Models\SalesPage;
use App\Shop\Models\Seller;
use App\Shop\Services\SalesPageService;

/**
 * Create a sales page against one of the seller's products. A product may have
 * many pages (e.g. per ad campaign); each gets a globally-unique public slug.
 */
class CreateSalesPage
{
    public function __construct(private readonly SalesPageService $pages) {}

    public function execute(Seller $seller, SalesPageData $data): SalesPage
    {
        if (! $seller->canSell()) {
            throw ShopException::notApproved();
        }

        $product = Product::where('seller_id', $seller->getKey())->findOrFail($data->productId);

        return SalesPage::create([
            'seller_id' => $seller->getKey(),
            'product_id' => $product->getKey(),
            'name' => $data->name,
            'slug' => $this->pages->uniqueSlug($data->name),
            'status' => SalesPageStatus::Draft,
            'sections' => $data->sections,
            'theme' => $data->theme,
            'seo' => $data->seo,
            'tracking' => $data->tracking,
            'version' => 1,
        ]);
    }
}
