<?php

declare(strict_types=1);

namespace App\Shop\DTOs;

use App\Shop\Enums\ProductType;

/** Validated product input (create/update). Immutable; built from a Form Request. */
final readonly class ProductData
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<VariantData>  $variants
     */
    public function __construct(
        public ProductType $type,
        public string $name,
        public ?string $summary,
        public ?string $description,
        public int $priceAmount,
        public int $priceAssetId,
        public ?int $comparePriceAmount,
        public bool $requiresShipping,
        public array $attributes,
        public array $variants,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $type = $input['type'] instanceof ProductType ? $input['type'] : ProductType::from($input['type']);

        return new self(
            type: $type,
            name: (string) $input['name'],
            summary: $input['summary'] ?? null,
            description: $input['description'] ?? null,
            priceAmount: (int) ($input['price_amount'] ?? 0),
            priceAssetId: (int) $input['price_asset_id'],
            comparePriceAmount: isset($input['compare_price_amount']) ? (int) $input['compare_price_amount'] : null,
            requiresShipping: (bool) ($input['requires_shipping'] ?? $type->requiresShipping()),
            attributes: $input['attributes'] ?? [],
            variants: array_map(
                fn (array $v) => VariantData::fromArray($v),
                $input['variants'] ?? [],
            ),
        );
    }
}
