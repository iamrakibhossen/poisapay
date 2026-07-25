<?php

declare(strict_types=1);

namespace App\Sell\Http\Requests;

use App\Sell\DTOs\ProductData;
use App\Sell\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Shared validation for creating and updating a product (+ its variants). */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ProductType::class)],
            'name' => ['required', 'string', 'max:190'],
            'summary' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string', 'max:20000'],
            'price_amount' => ['required', 'integer', 'min:0'],
            'price_asset_id' => ['required', 'integer', Rule::exists('assets', 'id')->where('is_active', true)],
            'compare_price_amount' => ['nullable', 'integer', 'min:0'],
            'requires_shipping' => ['boolean'],
            'attributes' => ['array'],
            'variants' => ['array', 'max:200'],
            'variants.*.id' => ['nullable', 'uuid'],
            'variants.*.options' => ['required', 'array', 'min:1'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.price_amount' => ['nullable', 'integer', 'min:0'],
            'variants.*.weight_grams' => ['nullable', 'integer', 'min:0'],
            'variants.*.sku' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function toData(): ProductData
    {
        return ProductData::fromArray($this->validated());
    }
}
