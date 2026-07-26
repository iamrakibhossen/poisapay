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
     * A premium, high-converting starter — a real header/footer + variant-based
     * sections, personalised with the product's name/summary in the hero. Commerce
     * blocks pull price/checkout from render context; content blocks fall back to
     * schema defaults, so the page reads well immediately and the seller just tweaks
     * copy. (Aligned with the {@see \App\Shop\Builder\Templates\TemplateLibrary}
     * quality bar.)
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
            $node('header', ['cta' => 'Buy now']),
            $node('hero', array_filter([
                'variant' => 'centered',
                'eyebrow' => 'New',
                'headline' => $product->name,
                'tagline' => 'Everything you need, in one place',
                'desc' => $product->summary ?: null,
                'btn' => 'Buy now',
            ])),
            $node('logos', ['heading' => 'Trusted by teams everywhere']),
            $node('features', ['variant' => 'iconTop', 'cols' => 3, 'eyebrow' => 'Features', 'heading' => 'Everything you get']),
            $node('benefits', ['eyebrow' => 'Benefits', 'heading' => 'Why you’ll love it']),
            $node('testimonials', ['variant' => 'cards', 'cols' => 3, 'heading' => 'Loved by buyers']),
            $node('guarantee'),
            $node('faq', ['variant' => 'accordion']),
            $node('product'),
            $node('cta-banner', ['variant' => 'gradient', 'heading' => 'Ready to get started?', 'sub' => 'Instant, secure checkout.', 'btn' => 'Buy now']),
            $node('footer', [
                'tagline' => 'Made with care.',
                'links' => [
                    ['label' => 'Features', 'url' => '#features'],
                    ['label' => 'FAQ', 'url' => '#faq'],
                ],
                'socialLinks' => [
                    ['platform' => 'x', 'url' => 'https://x.com'],
                    ['platform' => 'instagram', 'url' => 'https://instagram.com'],
                    ['platform' => 'linkedin', 'url' => 'https://linkedin.com'],
                ],
            ]),
        ]);

        return (new BuilderDocument($root))->toArray();
    }
}
