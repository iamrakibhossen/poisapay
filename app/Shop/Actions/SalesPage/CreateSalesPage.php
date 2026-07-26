<?php

declare(strict_types=1);

namespace App\Shop\Actions\SalesPage;

use App\Shop\Builder\BuilderDocument;
use App\Shop\Builder\BuilderNode;
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
            // Ship a ready-to-edit starter design so a generated page is never blank.
            'sections' => $data->sections ?: $this->starterDocument($product),
            'theme' => $data->theme,
            'seo' => $data->seo,
            'tracking' => $data->tracking,
            'version' => 1,
        ]);
    }

    /**
     * A demo sales page built from the standard high-converting block sequence.
     * Commerce blocks pull the product's name/price/checkout from render context;
     * content blocks fall back to their schema defaults, so the page reads well
     * immediately and the seller just tweaks copy.
     *
     * @return array<string, mixed>
     */
    private function starterDocument(Product $product): array
    {
        $node = fn (string $type, array $props = []): BuilderNode => new BuilderNode(
            id: BuilderNode::newId(),
            type: $type,
            props: $props,
        );

        $root = new BuilderNode(id: 'root', type: 'page', children: [
            $node('hero', array_filter([
                'headline' => $product->name,
                'desc' => $product->summary ?: null,
            ])),
            $node('features'),
            $node('benefits'),
            $node('testimonials'),
            $node('guarantee'),
            $node('faq'),
            $node('product'),
            $node('cta'),
        ]);

        return (new BuilderDocument($root))->toArray();
    }
}
