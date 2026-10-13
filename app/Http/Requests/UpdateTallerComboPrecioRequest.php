<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Precio por categoría de un combo de talleres (13/10/2026) — mismas
 * reglas de forma que Store.
 */
class UpdateTallerComboPrecioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'nullable|integer|exists:categories,id',
            'precio'      => 'required|numeric|min:0',
        ];
    }
}
