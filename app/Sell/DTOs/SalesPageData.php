<?php

declare(strict_types=1);

namespace App\Sell\DTOs;

/** Validated sales-page builder input. Flexible config travels as arrays (jsonb). */
final readonly class SalesPageData
{
    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  array<string, mixed>  $theme
     * @param  array<string, mixed>  $seo
     * @param  array<string, mixed>  $tracking
     */
    public function __construct(
        public string $productId,
        public string $name,
        public array $sections,
        public array $theme,
        public array $seo,
        public array $tracking,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            productId: (string) $input['product_id'],
            name: (string) $input['name'],
            sections: $input['sections'] ?? [],
            theme: $input['theme'] ?? [],
            seo: $input['seo'] ?? [],
            tracking: $input['tracking'] ?? [],
        );
    }
}
