<?php

declare(strict_types=1);

namespace App\Sell\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Buyer opening a refund request: full, or partial with an amount (minor units). */
class RequestRefundRequest extends FormRequest
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
            'order_id' => ['required', 'uuid', Rule::exists('sell_orders', 'id')],
            'type' => ['required', Rule::in(['full', 'partial'])],
            'amount' => ['nullable', 'integer', 'min:1', 'required_if:type,partial'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
