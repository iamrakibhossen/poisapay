<?php

declare(strict_types=1);

namespace App\Sell\DTOs;

/** One product variant (e.g. Size=M, Color=Black) with its own price/stock. */
final readonly class VariantData
{
    /**
     * @param  array<string, string>  $options
     */
    public function __construct(
        public array $options,
        public ?int $priceAmount,
        public ?int $stock,
        public ?string $sku,
        public ?int $weightGrams,
        public ?string $id = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            options: $input['options'] ?? [],
            priceAmount: isset($input['price_amount']) ? (int) $input['price_amount'] : null,
            stock: array_key_exists('stock', $input) && $input['stock'] !== null ? (int) $input['stock'] : null,
            sku: $input['sku'] ?? null,
            weightGrams: isset($input['weight_grams']) ? (int) $input['weight_grams'] : null,
            id: $input['id'] ?? null,
        );
    }
}
