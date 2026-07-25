<?php

declare(strict_types=1);

namespace App\Sell\Http\Requests;

use App\Sell\DTOs\CheckoutData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
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
            'product_id' => ['required', 'uuid', Rule::exists('sell_products', 'id')->whereNull('deleted_at')],
            'variant_id' => ['nullable', 'uuid', Rule::exists('sell_product_variants', 'id')],
            'quantity' => ['integer', 'min:1', 'max:999'],
            'sales_page_id' => ['nullable', 'uuid'],
            'funnel_id' => ['nullable', 'uuid'],
            // Client-supplied so a double-submit is idempotent (no duplicate order).
            'idempotency_key' => ['required', 'string', 'max:190'],
        ];
    }

    public function toData(): CheckoutData
    {
        return CheckoutData::fromArray($this->validated());
    }
}
