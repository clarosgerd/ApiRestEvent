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
            // Multi-uso (13/09/2026) — sometimes, sin fallback en el
            // controller (a diferencia de store()): si un PUT viejo no lo
            // manda, el código conserva su max_uses actual, nunca se
            // resetea a 1 sin querer.
            'max_uses'         => 'sometimes|nullable|integer|min:1|max:10000',
        ];
    }
}
