<?php

declare(strict_types=1);

namespace App\Sell\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Seller/operator responding to a refund request (approve or reject) with a note. */
class RespondRefundRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
