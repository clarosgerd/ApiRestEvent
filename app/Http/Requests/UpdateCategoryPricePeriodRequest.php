<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Precios por período (12/08/2026) — ver PRD-precios-periodos-fechas.md.
 * Mismas reglas de forma que Store — el chequeo de overlap (excluyéndose
 * a sí mismo) vive en el controller.
 */
class UpdateCategoryPricePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'      => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
        ];
    }
}
