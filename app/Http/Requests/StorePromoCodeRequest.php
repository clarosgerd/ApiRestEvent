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
            // Multi-uso (13/09/2026) — sometimes|nullable, NO required: así
            // un admin-eventos viejo (que todavía no manda este campo)
            // sigue creando códigos contra la API nueva sin romperse; el
            // controller lo default-ea a 1 cuando falta. Tope de 10000
            // como guardarraíl técnico, no una regla de negocio real.
            'max_uses'         => 'sometimes|nullable|integer|min:1|max:10000',
        ];
    }
}
