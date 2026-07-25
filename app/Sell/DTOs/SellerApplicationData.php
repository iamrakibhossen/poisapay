<?php

declare(strict_types=1);

namespace App\Sell\DTOs;

/**
 * Validated seller-application input. Immutable; built from the Form Request so
 * actions receive typed data, never a raw array.
 */
final readonly class SellerApplicationData
{
    /**
     * @param  list<string>  $categories
     */
    public function __construct(
        public ?string $brandName,
        public ?string $bio,
        public ?string $website,
        public ?string $country,
        public array $categories,
        public ?int $settlementAssetId,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            brandName: $input['brand_name'] ?? null,
            bio: $input['bio'] ?? null,
            website: $input['website'] ?? null,
            country: $input['country'] ?? null,
            categories: array_values($input['categories'] ?? []),
            settlementAssetId: isset($input['settlement_asset_id']) ? (int) $input['settlement_asset_id'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'brand_name' => $this->brandName,
            'bio' => $this->bio,
            'website' => $this->website,
            'country' => $this->country,
            'categories' => $this->categories,
            'settlement_asset_id' => $this->settlementAssetId,
        ];
    }
}
