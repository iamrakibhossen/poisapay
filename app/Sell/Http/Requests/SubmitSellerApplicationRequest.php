<?php

declare(strict_types=1);

namespace App\Sell\Http\Requests;

use App\Sell\DTOs\SellerApplicationData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitSellerApplicationRequest extends FormRequest
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
            'brand_name' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'url', 'max:190'],
            'country' => ['nullable', 'string', 'size:2'],
            'categories' => ['required', 'array', 'min:1', 'max:20'],
            'categories.*' => ['string', 'max:32'],
            'settlement_asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('is_active', true)],
            'terms' => ['accepted'],
        ];
    }

    public function toData(): SellerApplicationData
    {
        return SellerApplicationData::fromArray($this->validated());
    }
}
