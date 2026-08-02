<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePromoCodeRequest extends FormRequest
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
        return [
            'event_id'         => 'required|integer|exists:eventos,id',
            'promo_code'       => 'required|string|max:30|unique:promo_codes,promo_code',
            'price'            => 'nullable|numeric|min:0',
            'discount_type'    => 'nullable|string|in:fixed_price,percentage',
            'discount_percent' => 'nullable|numeric|min:0|max:1',
            'status'           => 'nullable|boolean',
        ];
    }
}
