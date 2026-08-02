<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromoCodeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $promoCode = $this->route('promo_code') ?? $this->route('promoCode');

        return [
            'promo_code'       => [
                'sometimes', 'string', 'max:30',
                Rule::unique('promo_codes', 'promo_code')->ignore($promoCode?->id),
            ],
            'price'            => 'sometimes|nullable|numeric|min:0',
            'discount_type'    => 'sometimes|nullable|string|in:fixed_price,percentage',
            'discount_percent' => 'sometimes|nullable|numeric|min:0|max:1',
            'status'           => 'sometimes|nullable|boolean',
        ];
    }
}
