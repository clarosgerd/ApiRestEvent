<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Precios por período para talleres (12/10/2026) — mismas reglas de forma
 * que Store; el chequeo de overlap (excluyéndose a sí mismo) vive en el
 * controller.
 */
class UpdateTallerPricePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'      => 'required|string|max:255',
            'category_id' => 'nullable|integer|exists:categories,id',
            'price'       => 'required|numeric|min:0',
            'price_usd'   => 'nullable|numeric|min:0',
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
        ];
    }
}
