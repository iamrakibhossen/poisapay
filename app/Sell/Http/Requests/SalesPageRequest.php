<?php

declare(strict_types=1);

namespace App\Sell\Http\Requests;

use App\Sell\DTOs\SalesPageData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesPageRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'sections' => ['array', 'max:60'],
            'sections.*.type' => ['required', 'string', 'max:40'],
            'theme' => ['array'],
            'seo' => ['array'],
            'tracking' => ['array'],
        ];
    }

    public function toData(): SalesPageData
    {
        return SalesPageData::fromArray($this->validated());
    }
}
