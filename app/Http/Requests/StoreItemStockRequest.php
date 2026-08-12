<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Kit/tallas/stock (11/08/2026) — ver
 * PRD-kit-tallas-stock-lista-espera.md.
 */
class StoreItemStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'talla'          => 'nullable|string|max:20',
            'sexo'           => 'nullable|in:masculino,femenino,unisex',
            'cantidad_total' => 'required|integer|min:0',
        ];
    }
}
