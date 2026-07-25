<?php

declare(strict_types=1);

namespace App\Sell\Actions\SalesPage;

use App\Sell\DTOs\SalesPageData;
use App\Sell\Enums\SalesPageStatus;
use App\Sell\Exceptions\SellException;
use App\Sell\Models\Product;
use App\Sell\Models\SalesPage;
use App\Sell\Models\Seller;
use App\Sell\Services\SalesPageService;

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
            throw SellException::notApproved();
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
